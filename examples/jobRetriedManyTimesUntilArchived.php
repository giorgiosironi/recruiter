#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Timeless as T;

use Recruiter\Recruiter;
use Recruiter\AlwaysFail;
use Recruiter\RetryPolicy;
use Recruiter\Worker;

$db = (new MongoClient())->selectDB('recruiter');
$db->drop();

$recruiter = new Recruiter($db);

(new AlwaysFail())
    ->asJobOf($recruiter)
    ->retryManyTimes(5, T\second(1))
    ->inBackground()
    ->execute();

$worker = $recruiter->hire();
while (true) {
    printf("Try to do my work\n");
    $assignments = $recruiter->assignJobsToWorkers();
    if ($assignments === 0) break;
    $worker->work();
    usleep(1200 * 1000);
}
