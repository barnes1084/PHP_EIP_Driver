#! /usr/bin/env php
<?php
namespace Goodyear\GTA;
require_once(__DIR__ . '/bootstrap.php');

use Goodyear\GTA\Common\StompClient;
use Goodyear\GTA\Common\Logger\Logger;
use Goodyear\GTA\Common\Logger\LoggerContext;
use Goodyear\GTA\Common\Using;
use Goodyear\GTA\Common\Configuration\Format\GtaConfig as Configuration;
use Goodyear\GTA\Common\Configuration\Format\Application\Processor\ConfigProcessor;

$arguments = Common\Arguments::get(
    $argv,
    [
        '-s' => [
            'documentation' => 'Start date for metric collection (YYYY-MM-DD hh24:mm)',
            'type' => 'string',
            'default' => null,
            'alias' => '--start-date'
        ],
        '-i' => [
            'documentation' => 'Number of minutes for metric collection',
            'type' => 'number',
            'default' => null,
            'alias' => '--interval-minutes'
        ],
    ],
    [
        "Script to store metrics for reporting"
    ]
);

Logger::setPrefix(Logger::bgColor('blue').("enqueuer-load-test").Logger::color('normal'));


//params
$start_str = $arguments['-s'];
$interval_min = $arguments['-i'];


function holdTimeByReason($em, $reason_codes, $report_start, $report_end) {
    $sql = "select oc.reason_code, sum(oc.seconds_awaiting_execution) total_seconds
        from
        (
        select 
        l.name, tt.tire_code, co.timestamp, co.closed_time, l.name, co.accepted, co.delivered, co.rejection_reason,
        co.id, co.seconds_awaiting_execution, co.seconds_executing, co.seconds_since_previous, priority_code.reason_code
        from cavity_orders co
        join cavity_requests cr
        on co.cavity_request_id=cr.id
        join locations l
        on cr.cavity_id=l.id
        join tire_types tt
        on cr.tire_type_id=tt.id
        left outer join 
        (" . holdReasonPriorityQuery($reason_codes) . ") priority_code
        on co.id=priority_code.order_id
        where co.timestamp >= TO_DATE('" . $report_start->format('Y-m-d H:i:s') . "', 'YYYY-MM-DD" . '"' . " " . '"' . "HH24:MI:SS')
        and co.timestamp < TO_DATE('" . $report_end->format('Y-m-d H:i:s') . "', 'YYYY-MM-DD" . '"' . " " . '"' . "HH24:MI:SS')
        and reason_code is not null
        ) oc
        group by oc.reason_code"
        ;
    $result = $em->getConnection()->executeQuery($sql);
    foreach($result->fetchAll() as $reason_code_ct) {
        $reason_code = $reason_code_ct['REASON_CODE'];
        $ct = $reason_code_ct['TOTAL_SECONDS'];
        Logger::info('Reason code ' . strval($reason_code) . ' for ' . strval($ct) . ' seconds.');
        persistMetric($em, $report_start, 'secondsForHoldReasonCode', strval($reason_code), $ct);
    }
}

function holdTimeByTireCode($em, $code_type, $threshold, $reason_codes, $painted_codes, $report_start, $report_end) {
    $sql = "select oc.tire_code, sum(oc.seconds_awaiting_execution) total_seconds
        from
        (
        select 
        l.name, tt.tire_code, co.timestamp, co.closed_time, l.name, co.accepted, co.delivered, co.rejection_reason,
        co.id, co.seconds_awaiting_execution, co.seconds_executing, co.seconds_since_previous, priority_code.reason_code
        from cavity_orders co
        join cavity_requests cr
        on co.cavity_request_id=cr.id
        join locations l
        on cr.cavity_id=l.id
        join tire_types tt
        on cr.tire_type_id=tt.id
        left outer join 
        (" . holdReasonPriorityQuery($reason_codes) . ") priority_code
        on co.id=priority_code.order_id
        where co.timestamp >=  TO_DATE('" . $report_start->format('Y-m-d H:i:s') . "', 'YYYY-MM-DD" . '"' . " " . '"' . "HH24:MI:SS')
        and co.timestamp < TO_DATE('" . $report_end->format('Y-m-d H:i:s') . "', 'YYYY-MM-DD" . '"' . " " . '"' . "HH24:MI:SS')
        ) oc
        where oc.reason_code in (" . implode(",",$painted_codes) . ")
        group by oc.tire_code
        order by oc.tire_code
        "
        ;
    $result = $em->getConnection()->executeQuery($sql);
    foreach($result->fetchAll() as $tire_code_ct) {
        $tire_code = $tire_code_ct['TIRE_CODE'];
        $ct = $tire_code_ct['TOTAL_SECONDS'];
        if (intval($ct) > $threshold) {
            Logger::info('Tire code (' . $code_type . ') '. strval($tire_code) . ' for ' . strval($ct) . ' seconds.');
            persistMetric($em, $report_start, $code_type.'SecondsForTireCode', strval($tire_code), $ct);
        }
    }
}

function holdTimeByCuringArea($em, $reason_codes, $curing_area_codes, $report_start, $report_end) {
    $sql = "
        select oc.pit, sum(oc.seconds_awaiting_execution) total_seconds
        from
        (
        select 
        case when l.pit is null then l.trench else l.pit end as pit, 
        tt.tire_code, co.timestamp, co.closed_time, co.accepted, co.delivered, co.rejection_reason,
        co.id, co.seconds_awaiting_execution, co.seconds_executing, co.seconds_since_previous, priority_code.reason_code
        from cavity_orders co
        join cavity_requests cr
        on co.cavity_request_id=cr.id
        join locations l
        on cr.cavity_id=l.id
        join tire_types tt
        on cr.tire_type_id=tt.id
        left outer join 
        (" . holdReasonPriorityQuery($reason_codes) . ") priority_code
        on co.id=priority_code.order_id
        where co.timestamp >=  TO_DATE('" . $report_start->format('Y-m-d H:i:s') . "', 'YYYY-MM-DD" . '"' . " " . '"' . "HH24:MI:SS')
        and co.timestamp < TO_DATE('" . $report_end->format('Y-m-d H:i:s') . "', 'YYYY-MM-DD" . '"' . " " . '"' . "HH24:MI:SS')
        ) oc
        where oc.reason_code in (" . implode(",",$curing_area_codes) . ") 
        group by oc.pit
        order by oc.pit"
    ;
    $result = $em->getConnection()->executeQuery($sql);
    foreach($result->fetchAll() as $pit_ct) {
        $pit = $pit_ct['PIT'];
        $ct = $pit_ct['TOTAL_SECONDS'];
        Logger::info('PIT '. strval($pit) . ' for ' . strval($ct) . ' seconds.');
        persistMetric($em, $report_start, 'secondsForPitReasonCode', strval($pit), $ct);
    }
}

function holdReasonPriorityQuery($reason_codes) {
    $query_text = "select order_id, 
        case ";
    foreach($reason_codes as $reason_code) {
        $query_text .= "when reason_" . strval($reason_code) . " > 0 then " . strval($reason_code) . " ";
    }
    $query_text .= "
        else 0
        end reason_code
        from 
        (
        " . holdReasonPivot($reason_codes) . "
        ) piv
        ";
    return $query_text;
}

function holdReasonPivot($reason_codes) {
    $query_text = "SELECT * FROM
        (
          SELECT order_id, reason_code
          FROM order_hold_reasons
        )
        PIVOT
        (
          COUNT(reason_code)
          FOR reason_code IN ( ";
    foreach($reason_codes as $reason_code) {
        $query_text .= strval($reason_code) . ' as reason_' . strval($reason_code) . ', ';
    }
    //remove last comma
    $query_text = substr($query_text, 0, -2);
    $query_text .= " 
          )
        )";
    return $query_text;
}

function persistMetric($em, $report_start, $type, $key, $val) {
    $srm_row = new Models\SystemReportMetric();
    $srm_row->setStartTime($report_start);
    $srm_row->setMetricType($type);
    $srm_row->setMetricKey($key);
    $srm_row->setMetricValue($val);
    $em->persist($srm_row);
}

function emailAlert($txt, $email_to) {
    $to = implode(",", $email_to);
    if (strlen($to) > 0) {
        $subject = 'GTA - ' . gethostname() . ' - system health check alert';
        Logger::info("Sending alert email");
        mail($to,$subject,$txt);
    }
}

function getReportTimes($times) {
    $current_time = new \DateTime();
    $report_end = null;
    $report_end_ix = null;
    $report_start = null;
    $minutes_to_add = null;
    foreach($times as $ix=>$hhmm) { //see if any of the report times are within the last hour
        $hh_mm = splitTimes($hhmm);
        $report_time = new \DateTime();
        $report_time->setTime($hh_mm['hours'],$hh_mm['minutes']);
        if ($report_time > $current_time) {
            $report_time = $report_time->sub(new \DateInterval('P1D'));
        }
        $difference = $report_time->diff($current_time);
        $minutes_difference = $difference->days*24*60+$difference->h*60+$difference->i;
        if ($minutes_difference < 60) {
            $report_end = $report_time;
            $report_end_ix = $ix;
            break;
        }
    }
    if ($report_end) { //if we found a report time within the last hour, get the start time
        $report_start = clone $report_end;
        if(count($times) > 1) {
            if ($ix == 0) {
                $start_times = splitTimes($times[count($times)-1]);
                $end_times = splitTimes($times[$ix]);
                $minutes_to_add = (24*60-$start_times['hours']*60+-$start_times['minutes']) + ($end_times['hours']*60+$end_times['minutes']);
            } else {
                $start_times = splitTimes($times[$ix-1]);
                $end_times = splitTimes($times[$ix]);
                $minutes_to_add = ($end_times['hours']*60+$end_times['minutes']) - ($start_times['hours']*60+$start_times['minutes']);
            }
            $report_start->sub(new \DateInterval('PT'.$minutes_to_add.'M'));
        } else { //just one time, start date should be 1 day before end date
            $report_start->sub(new \DateInterval('P1D'));
            $minutes_to_add = 60*24;
        }
    }
    return ['start' => $report_start, 'end' => $report_end, 'report_interval' => $minutes_to_add];
}

function splitTimes($hhmm) {
    $hh_mm = explode(":", $hhmm);
    return ['hours' => intval($hh_mm[0]), 'minutes' => intval($hh_mm[1])];
}

//MAIN PROGRAM EXECUTION
try {
    Logger::setPrefix(Logger::bgColor('blue').('report-metrics').Logger::color('normal'));
    Logger::info("Start system report metrics");
    $data_mapper = Common\DataMapper::get();
    $em = $data_mapper->getEntityManager();
    $config = Configuration::get();
    $email_to = $config->monitoring->developer_monitoring_emails;
    $priority = ConfigProcessor::getProcessorProperty(
        $config,
        ConfigProcessor::getProcessorDestination($config, 'OrderOnHoldProcessor'),
        'hold_priority'
    );
    $painted_reasons = ConfigProcessor::getProcessorProperty(
        $config,
        ConfigProcessor::getProcessorDestination($config, 'OrderOnHoldProcessor'),
        'painted_reasons'
    );
    $tire_reasons = ConfigProcessor::getProcessorProperty(
        $config,
        ConfigProcessor::getProcessorDestination($config, 'OrderOnHoldProcessor'),
        'tire_reasons'
    );
    $curing_area_reasons = ConfigProcessor::getProcessorProperty(
        $config,
        ConfigProcessor::getProcessorDestination($config, 'OrderOnHoldProcessor'),
        'curing_area_reasons'
    );
    $daily_threshold = 60*10;
    
    $report_start = null;
    $report_end = null;
    $report_interval = null;
    if (!($start_str && $interval_min)) {
        $config_times = $config->monitoring->report_start_times;
        if ($config_times && count($config_times) > 0) {
            $report_times = getReportTimes($config_times);
            $report_start = $report_times['start'];
            $report_end = $report_times['end'];
            $report_interval = $report_times['report_interval'];
        }
    } else {
        $report_start = new \DateTime($start_str);
        $report_interval = $interval_min;
        $report_end = clone $report_start;
        $report_end->add(new \DateInterval('PT'.strval($interval_min).'M'));
    }
    if ($report_start && $report_interval) {
        Logger::info('Start time: ' . $report_start->format('Y-m-d H:i:s'));
        Logger::info('Interval (min): ' . strval($report_interval));
        if ($priority && count($priority) > 0) {
            $report_threshold = $daily_threshold * ($report_interval / (60*24));
            holdTimeByReason($em, $priority, $report_start, $report_end);
            if ($painted_reasons && count($painted_reasons) > 0) {
                holdTimeByTireCode($em, 'painted', $report_threshold, $priority, $painted_reasons, $report_start, $report_end);
            }
            if ($tire_reasons && count($tire_reasons) > 0) {
                holdTimeByTireCode($em, 'tire', $report_threshold, $priority, $tire_reasons, $report_start, $report_end);
            }
            if ($curing_area_reasons && count($curing_area_reasons) > 0) {
                holdTimeByCuringArea($em, $priority, $curing_area_reasons, $report_start, $report_end);
            }
        }
    }
    
    $em->flush();
    
    Logger::info("End system report metrics");

} catch (\Throwable $e) {
    Logger::error($e->getMessage());
    emailAlert($e->getMessage(), $email_to);
    Logger::error($e);
}

