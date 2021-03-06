<?php

namespace Recruiter;

use MongoDB;

class Recruiter
{
    private $db;
    private $jobs;
    private $workers;

    public function __construct(MongoDB $db)
    {
        $this->db = $db;
        $this->jobs = new Job\Repository($db);
        $this->workers = new Worker\Repository($db, $this);
    }

    public function hire()
    {
        return Worker::workFor($this, $this->workers);
    }

    public function jobOf(Workable $workable)
    {
        return new JobToSchedule(
            Job::around($workable, $this->jobs)
        );
    }

    public function assignJobsToWorkers()
    {
        $roster = $this->db->selectCollection('roster');
        $scheduled = $this->db->selectCollection('scheduled');
        $workersPerUnit = 42;

        return Worker::pickAvailableWorkers($roster, $workersPerUnit,
            function($worksOn, $workers) use ($scheduled, $roster)
        {
            return Job::pickReadyJobsForWorkers($scheduled, $worksOn, $workers,
                function($worksOn, $workers, $jobs) use($scheduled, $roster)
            {
                list($assignments, $jobs, $workers) = $this->combineJobsWithWorkers($jobs, $workers);

                Job::lockAll($scheduled, $jobs);
                Worker::assignJobsToWorkers($roster, $jobs, $workers);

                return $assignments;
            });
        });
    }

    public function scheduledJob($id)
    {
        return $this->jobs->scheduled($id);
    }

    public function createCollectionsAndIndexes()
    {
        $this->db->command(['collMod' => 'scheduled', 'usePowerOf2Sizes' => true]);
        $this->db->selectCollection('scheduled')->ensureIndex([
            'scheduled_at' => 1,
            'active' => 1,
            'locked' => 1,
            'tags' => 1,
        ]);

        $this->db->command(['collMod' => 'archived', 'usePowerOf2Sizes' => true]);
        $this->db->selectCollection('archived')->ensureIndex([
            'created_at' => 1,
        ]);

        $this->db->command(['collMod' => 'roster', 'usePowerOf2Sizes' => true]);
        $this->db->selectCollection('roster')->ensureIndex([
            'available' => 1,
        ]);
    }

    private function combineJobsWithWorkers($jobs, $workers)
    {
        $assignments = min(count($workers), count($jobs));
        $workers = array_slice($workers, 0, $assignments);
        $jobs = array_slice($jobs, 0, $assignments);
        return [$assignments, $jobs, $workers];
    }
}
