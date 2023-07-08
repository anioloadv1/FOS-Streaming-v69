<?php
/**
 * Created by Tyfix 2015
 */
include('config.php');
logincheck();

//Create settings if not exists
$settings = Setting::first();
if (is_null($settings)) {
    $settings = new Setting;
    $settings->webip = $_SERVER['SERVER_ADDR'];
    $settings->webport = 8000;
    $settings->save();
}
$all = Stream::all()->count();
$online = Stream::where('running', '=', 1)->count();
$offline = Stream::where('running', '=', 0)->count();
$space_pr = 0;
$space_free = round((disk_free_space('/')) / 1048576, 1);
$space_total = round((disk_total_space('/')) / 1048576, 1);
$spaceUse = $space_total - $space_free;
//$space_pr = (int)(100 * ($space_free / $space_total));
$space_pr = round($spaceUse / ($space_total) * 100, 2);
$cpu_usage = "";
$cpu_total = "";
if (stristr(PHP_OS, 'win')) {
    $cpu_usage = 2;
    $cpu_total = 10;
    $cpu_pr = $cpu_usage / $cpu_total * 100;
    $mem_usage = 20;
    $mem_total = 120;
    $mem_pr = (int)(100 * ($mem_usage / $mem_total));

} else {
    $loads = sys_getloadavg();
    $core_nums = trim(shell_exec("grep -P '^processor' /proc/cpuinfo|wc -l"));
    //$cpu_usage = $loads[0];
    //$cpu_total = $core_nums + 6;
    //$cpu_pr = round($cpu_usage / ($cpu_total) * 100, 2);
    
    //CPU 1 core
    $cpu_total = 100;
    $cpu_pr = $cpu_total - shell_exec("echo \"$(vmstat 1 2|tail -1|awk '{print $15}')\"");
    $cpu_usage = $cpu_pr;
    
    
    $free = shell_exec('free');
    $free = (string)trim($free);
    $free_arr = explode("\n", $free);
    $mem = explode(" ", $free_arr[1]);
    $mem = array_filter($mem);
    $mem = array_merge($mem);
    $mem_usage = $mem[2];
    $mem_total = $mem[1];
    //$mem_pr = $mem[2] / $mem[1] * 100;
    $mem_pr= round($mem_usage / ($mem_total) * 100, 2);
}

//Network by python
$checkNetwork = shell_exec("/usr/bin/python3.9 /home/fos-streaming/fos/network/monitor.py");

$jsonDataNetwork = json_decode($checkNetwork);


//Network IN
$netInTotal = 100;
$netIn_free = $netInTotal  - $jsonDataNetwork->in;
$netIn_pr = round($jsonDataNetwork->in / ($netInTotal) * 1000, 2);


$netOutTotal = 100;
$netOut_free = $netOutTotal  - $jsonDataNetwork->out;
$netOut_pr = round($jsonDataNetwork->out / ($netOutTotal) * 1000, 2);

$netIn = [];
$netIn['pr'] = $netIn_pr;
$netIn['count'] = $jsonDataNetwork->in;
$netIn['total'] = $netInTotal;

$netOut = [];
$netOut['pr'] = $netOut_pr;
$netOut['count'] = $jsonDataNetwork->out;
$netOut['total'] = $netOutTotal;


$space = [];
$space['pr'] = $space_pr;
$space['count'] = formatBytes((int)$spaceUse."000000");
$space['total'] = formatBytes((int)$space_total."000000");

$cpu = [];
$cpu['pr'] = $cpu_pr;
$cpu['count'] = $cpu_usage;
$cpu['total'] = $cpu_total;

$mem = [];
$mem['pr'] = $mem_pr;
$mem['count'] = formatBytes($mem_usage."000");
$mem['total'] = formatBytes($mem_total."000");


echo $template->view()
    ->make('dashboard')
    ->with('all', $all)
    ->with('online', $online)
    ->with('offline', $offline)
    ->with('space', $space)
    ->with('cpu', $cpu)
    ->with('mem', $mem)
    ->with('netin', $netIn)
    ->with('netout', $netOut)
    ->render();
