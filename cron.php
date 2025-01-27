<?php
if (isset($_SERVER['SERVER_ADDR'])) {
    if ($_SERVER['REMOTE_ADDR'] != $_SERVER['SERVER_ADDR']) {
        die('access is not permitted');
    }
}
include('config.php');
$setting = Setting::first();
foreach (Stream::where('pid', '!=', 0)->where('running', '=', 1)->get() as $stream) {
    if($setting->restart != 0) {
        //Se obtiene el time del stream
        $lastMod = $stream->updated_at;
        $restarTime = $setting->restart;
        $datetime1 = new DateTime($lastMod);//start time
        $datetime2 = new DateTime(date('Y-m-d H:i:s'));//end time
        $interval = $datetime1->diff($datetime2);


        if($interval->y != 0 || $interval->m != 0 || $interval->d != 0 || $interval->h >= $restarTime){
            $restart = true;
        } else {
            $restart = false;
        }

        if($restart){
            exec("sudo kill -9 $stream->pid");
            sleep(1);
        }
    }
    
    if (!checkPid($stream->pid)) {
        $stream->checker = 0;
        $checkstreamurl = shell_exec('/usr/bin/timeout 60s ' . $setting->ffprobe_path . ' -analyzeduration 10000000 -probesize 9000000 -i "' . $stream->streamurl . '" -v  quiet -print_format json -show_streams 2>&1');
        $streaminfo = (array)json_decode($checkstreamurl);
        if (count($streaminfo) > 0) {
            $pid = shell_exec(getTranscode($stream->id));
            $stream->pid = $pid;
            $stream->running = 1;
            $stream->status = 1;

        } else {
            $stream->running = 1;
            $stream->status = 2;
            shell_exec("kill -9 " . $stream->pid);
            shell_exec("/bin/rm -r /home/fos-streaming/fos/www/" . $setting->hlsfolder . "/" . $stream->id . "*");

            if ($stream->streamurl2) {
                $stream->checker = 2;
                echo "checking stream 2";
                $checkstreamurl = shell_exec('/usr/bin/timeout 60s ' . $setting->ffprobe_path . ' -analyzeduration 10000000 -probesize 9000000 -i "' . $stream->streamurl2 . '" -v  quiet -print_format json -show_streams 2>&1');
                $streaminfo = (array)json_decode($checkstreamurl);
                if (count($streaminfo) > 0) {
                    echo getTranscode($stream->id, 2);
                    $pid = shell_exec(getTranscode($stream->id, 2));
                    $stream->pid = $pid;
                    $stream->running = 1;
                    $stream->status = 1;
                } else {
                    $stream->running = 1;
                    $stream->status = 2;
                    shell_exec("kill -9 " . $stream->pid);
                    shell_exec("/bin/rm -r /home/fos-streaming/fos/www/" . $setting->hlsfolder . "/" . $stream->id . "*");
                    if ($stream->streamurl3) {
                        $stream->checker = 3;
                        $checkstreamurl = shell_exec('/usr/bin/timeout 60s ' . $setting->ffprobe_path . ' -analyzeduration 10000000 -probesize 9000000 -i "' . $stream->streamurl3 . '" -v  quiet -print_format json -show_streams 2>&1');
                        $streaminfo = (array)json_decode($checkstreamurl);
                        if (count($streaminfo) > 0) {
                            $pid = shell_exec(getTranscode($stream->id, 3));
                            $stream->pid = $pid;
                            $stream->running = 1;
                            $stream->status = 1;

                        } else {
                            $stream->running = 1;
                            $stream->status = 2;
                        }
                    }
                }
            }
        }
        $stream->save();
    }
}
foreach (Stream::where('restream', '=', 1)->where('running', '=', 1)->get() as $stream) {
    $stream->checker = 0;
    $checkstreamurl = shell_exec('/usr/bin/timeout 60s ' . $setting->ffprobe_path . ' -analyzeduration 10000000 -probesize 9000000 -i "' . $stream->streamurl . '" -v  quiet -print_format json -show_streams 2>&1');
    $streaminfo = (array)json_decode($checkstreamurl);
    if (count($streaminfo) > 0) {
        $stream->checker = 0;
    } else {
        if ($stream->streamurl2) {
            $checkstreamurl = shell_exec('/usr/bin/timeout 60s ' . $setting->ffprobe_path . ' -analyzeduration 10000000 -probesize 9000000 -i "' . $stream->streamurl2 . '" -v  quiet -print_format json -show_streams 2>&1');
            $streaminfo = (array)json_decode($checkstreamurl);
            if (count($streaminfo) > 0) {
                $stream->checker = 2;
            } else { // fail 2
                if ($stream->streamurl3) {

                    $checkstreamurl = shell_exec('/usr/bin/timeout 60s ' . $setting->ffprobe_path . ' -analyzeduration 10000000 -probesize 9000000 -i "' . $stream->streamurl3 . '" -v  quiet -print_format json -show_streams 2>&1');
                    $streaminfo = (array)json_decode($checkstreamurl);
                    if (count($streaminfo) > 0) {
                        $stream->checker = 3;
                    }
                }
            }
        }
    }
    $stream->save();
}
