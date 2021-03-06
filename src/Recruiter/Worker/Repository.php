<?php

namespace Recruiter\Worker;

use MongoDB;
use MongoCollection;
use Recruiter\Recruiter;
use Recruiter\Worker;

class Repository
{
    private $roster;
    private $recruiter;

    public function __construct(MongoDB $db, Recruiter $recruiter)
    {
        $this->roster = $db->selectCollection('roster');
        $this->recruiter = $recruiter;
    }

    public function save($worker)
    {
        $this->roster->save($worker->export());
    }

    public function refresh($worker)
    {
        $worker->updateWith(
            $this->roster->findOne(['_id' => $worker->id()])
        );
    }
}
