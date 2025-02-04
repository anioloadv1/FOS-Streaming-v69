<?php

function redirect($url, $time)
{
    echo "<script>
                window.setTimeout(function(){
                    window.location.href = '" . $url . "';
                }, " . $time . ");
            </script>";
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("location: index.php");
}

function logincheck()
{
    if (!isset($_SESSION['user_id'])) {
        header("location: index.php");
    }
}

function lists($list, $column)
{
    $columns = [];
    foreach ($list->toArray() as $key => $value) {
        array_push($columns, $value[$column]);
    }

    return $columns;
}

function checkPid($pid)
{
    exec("ps $pid", $output, $result);
    return count($output) >= 2 ? true : false;
}

function stop_stream($id)
{
    $stream = Stream::find($id);
    $setting = Setting::first();

    if (checkPid($stream->pid)) {
        shell_exec("sudo kill -9 " . $stream->pid);
        shell_exec("/bin/rm -r /home/fos-streaming/fos/www/" . $setting->hlsfolder . "/" . $stream->id . "*");
    }
    $stream->pid = "";
    $stream->running = 0;
    $stream->status = 0;

    $stream->save();
    sleep(2);
}


function getTranscode($id, $streamnumber = null)
{
    $stream = Stream::find($id);
    $setting = Setting::first();
    $trans = $stream->transcode;
    $ffmpeg = $setting->ffmpeg_path;
    $url = $stream->streamurl;
    if ($streamnumber == 2) {
        $url = $stream->streamurl2;
    }
    if ($streamnumber == 3) {
        $url = $stream->streamurl3;
    }
    

    $endofffmpeg = "";
    $endofffmpeg .= $stream->bitstreamfilter ? ' -bsf h264_mp4toannexb' : '';
    if($stream->rtmp) {
        $endofffmpeg .= ' -f flv '.$stream->rtmp. '  > /dev/null 2>/dev/null & echo $! ';
        
    } else {
        $endofffmpeg .= ' -hls_flags delete_segments ';
        $endofffmpeg .= ' -hls_list_size 8 /home/fos-streaming/fos/www/' . $setting->hlsfolder . '/' . $stream->id . '_.m3u8  > /dev/null 2>/dev/null & echo $! ';
    }

    if ($trans) {
        $ffmpeg .= ' -y';
        $ffmpeg .= ' -probesize ' . ($trans->probesize ? $trans->probesize : '15000000');
        $ffmpeg .= ' -analyzeduration ' . ($trans->analyzeduration ? $trans->analyzeduration : '12000000');
        $ffmpeg .= $stream->isrestream ? ' -re' : '';
        $ffmpeg .= $stream->cenc ? ' -cenc_decryption_key "' . ($stream->cenc).'"' : '';
        $ffmpeg .= ' -i ' . '"' . "$url" . '"';
        $ffmpeg .= ' -user_agent "' . ($setting->user_agent ? $setting->user_agent : 'FOS-Streaming') . '"';
        $ffmpeg .= ' -strict -2 -dn ';
        $ffmpeg .= $trans->scale ? ' -vf scale=' . ($trans->scale ? $trans->scale : '') : '';
        $ffmpeg .= $trans->audio_codec ? ' -acodec ' . $trans->audio_codec : '';
        '';
        $ffmpeg .= $trans->video_codec ? ' -vcodec ' . $trans->video_codec : '';
        $ffmpeg .= $trans->profile ? ' -profile:v ' . $trans->profile : '';
        $ffmpeg .= $trans->preset ? ' -preset ' . $trans->preset_values : '';
        $ffmpeg .= $trans->video_bitrate ? ' -b:v ' . $trans->video_bitrate . 'k' : '';
        $ffmpeg .= $trans->audio_bitrate ? ' -b:a ' . $trans->audio_bitrate . 'k' : '';
        $ffmpeg .= $trans->fps ? ' -r ' . $trans->fps : '';
        $ffmpeg .= $trans->minrate ? ' -minrate ' . $trans->minrate . 'k' : '';
        $ffmpeg .= $trans->maxrate ? ' -maxrate ' . $trans->maxrate . 'k' : '';
        $ffmpeg .= $trans->bufsize ? ' -bufsize ' . $trans->bufsize . 'k' : '';
        $ffmpeg .= $trans->aspect_ratio ? ' -aspect ' . $trans->aspect_ratio : '';
        $ffmpeg .= $trans->audio_sampling_rate ? ' -ar ' . $trans->audio_sampling_rate : '';
        $ffmpeg .= $trans->crf ? ' -crf ' . $trans->crf : '';
        $ffmpeg .= $trans->audio_channel ? ' -ac ' . $trans->audio_channel : '';
        $ffmpeg .= $trans->threads ? ' -threads ' . $trans->threads : '';
        $ffmpeg .= $trans->deinterlance ? ' -vf yadif' : '';
        $ffmpeg .= $endofffmpeg;
        return $ffmpeg;
    }

    $ffmpeg .= $stream->isrestream ? ' -re' : '';
    $ffmpeg .= $stream->cenc ? ' -cenc_decryption_key "' . ($stream->cenc).'"' : '';
    $ffmpeg .= ' -probesize 15000000 -analyzeduration 9000000 -i "' . $url . '"';
    $ffmpeg .= ' -user_agent "' . ($setting->user_agent ? $setting->user_agent : 'FOS-Streaming') . '"';
    $ffmpeg .= ' -c copy -c:a aac -b:a 128k';
    $ffmpeg .= $endofffmpeg;
    return $ffmpeg;
}

function formatBytes($size, $precision = 2)
{
    $base = log($size, 1024);
    $suffixes = array('', 'K', 'M', 'G', 'T');   

    return round(pow(1024, $base - floor($base)), $precision) .' '. $suffixes[floor($base)];
}

function getTranscodedata($id)
{
    $trans = Transcode::find($id);
    $setting = Setting::first();
    $ffmpeg = "ffmpeg";
    $ffmpeg .= ' -y';
    $ffmpeg .= ' -probesize ' . ($trans->probesize ? $trans->probesize : '15000000');
    $ffmpeg .= ' -analyzeduration ' . ($trans->analyzeduration ? $trans->analyzeduration : '12000000');
    $ffmpeg .= ' -i ' . '"' . "[input]" . '"';
    $ffmpeg .= ' -user_agent "' . ($setting->user_agent ? $setting->user_agent : 'FOS-Streaming') . '"';
    $ffmpeg .= ' -strict -2 -dn ';
    $ffmpeg .= $trans->scale ? ' -vf scale=' . ($trans->scale ? $trans->scale : '') : '';
    $ffmpeg .= $trans->audio_codec ? ' -acodec ' . $trans->audio_codec : '';
    $ffmpeg .= $trans->video_codec ? ' -vcodec ' . $trans->video_codec : '';
    $ffmpeg .= $trans->profile ? ' -profile:v ' . $trans->profile : '';
    $ffmpeg .= $trans->preset ? ' -preset ' . $trans->preset_values : '';
    $ffmpeg .= $trans->video_bitrate ? ' -b:v ' . $trans->video_bitrate . 'k' : '';
    $ffmpeg .= $trans->audio_bitrate ? ' -b:a ' . $trans->audio_bitrate . 'k' : '';
    $ffmpeg .= $trans->fps ? ' -r ' . $trans->fps : '';
    $ffmpeg .= $trans->minrate ? ' -minrate ' . $trans->minrate . 'k' : '';
    $ffmpeg .= $trans->maxrate ? ' -maxrate ' . $trans->maxrate . 'k' : '';
    $ffmpeg .= $trans->bufsize ? ' -bufsize ' . $trans->bufsize . 'k' : '';
    $ffmpeg .= $trans->aspect_ratio ? ' -aspect ' . $trans->aspect_ratio : '';
    $ffmpeg .= $trans->audio_sampling_rate ? ' -ar ' . $trans->audio_sampling_rate : '';
    $ffmpeg .= $trans->crf ? ' -crf ' . $trans->crf : '';
    $ffmpeg .= $trans->audio_channel ? ' -ac ' . $trans->audio_channel : '';
    $ffmpeg .= $trans->threads ? ' -threads ' . $trans->threads : '';
    $ffmpeg .= $trans->deinterlance ? ' -vf yadif' : '';
    $ffmpeg .= " output[HLS]";
    return $ffmpeg;
}


function start_stream($id)
{
    $stream = Stream::find($id);
    $setting = Setting::first();
    if ($stream->restream) {
        $stream->checker = 0;
        $stream->pid = null;
        $stream->running = 1;
        $stream->status = 1;
    } else {
        $stream->checker = 0;
        $checkstreamurl = shell_exec('' . $setting->ffprobe_path . ' -analyzeduration 1000000 -probesize 9000000 -i "' . $stream->streamurl . '" -v  quiet -print_format json -show_streams 2>&1');
        $streaminfo = json_decode($checkstreamurl, true);
        if ($streaminfo) {
            $transcodeShell = getTranscode($stream->id);
            $pid = shell_exec($transcodeShell);
            $stream->pid = $pid;
            $stream->running = 1;
            $stream->status = 1;
            $video = "";
            $audio = "";
            if (is_array($streaminfo)) {
                foreach ($streaminfo['streams'] as $info) {
                    if ($video == '') {
                        $video = ($info['codec_type'] == 'video' ? $info['codec_name'] : '');
                    }
                    if ($audio == '') {
                        $audio = ($info['codec_type'] == 'audio' ? $info['codec_name'] : '');
                    }

                }
                $stream->video_codec_name = $video;
                $stream->audio_codec_name = $audio;
            }
        } else {
            $stream->running = 1;
            $stream->status = 2;
            if (checkPid($stream->pid)) {
                shell_exec("kill -9 " . $stream->pid);
                shell_exec("/bin/rm -r /home/fos-streaming/fos/www/" . $setting->hlsfolder . "/" . $stream->id . "*");
            }

            if ($stream->streamurl2) {
                $stream->checker = 2;

                $checkstreamurl = shell_exec('' . $setting->ffprobe_path . ' -analyzeduration 1000000 -probesize 9000000 -i "' . $stream->streamurl . '" -v  quiet -print_format json -show_streams 2>&1');
                $streaminfo = json_decode($checkstreamurl, true);

                if ($streaminfo) {
                    $pid = shell_exec(getTranscode($stream->id, 2));
                    $stream->pid = $pid;
                    $stream->running = 1;
                    $stream->status = 1;
                    $video = "";
                    $audio = "";
                    if (is_array($streaminfo)) {
                        foreach ($streaminfo['streams'] as $info) {
                            if ($video == '') {
                                $video = ($info['codec_type'] == 'video' ? $info['codec_name'] : '');
                            }
                            if ($audio == '') {
                                $audio = ($info['codec_type'] == 'audio' ? $info['codec_name'] : '');
                            }
                        }
                        $stream->video_codec_name = $video;
                        $stream->audio_codec_name = $audio;
                    }
                } else {
                    $stream->running = 1;
                    $stream->status = 2;
                    if (checkPid($stream->pid)) {
                        shell_exec("kill -9 " . $stream->pid);
                        shell_exec("/bin/rm -r /home/fos-streaming/fos/www/" . $setting->hlsfolder . "/" . $stream->id . "*");
                    }
                    if ($stream->streamurl3) {
                        $stream->checker = 3;
                        $checkstreamurl = shell_exec('' . $setting->ffprobe_path . ' -analyzeduration 1000000 -probesize 9000000 -i "' . $stream->streamurl . '" -v  quiet -print_format json -show_streams 2>&1');
                        $streaminfo = json_decode($checkstreamurl, true);
                        if ($streaminfo) {
                            $pid = shell_exec(getTranscode($stream->id, 3));

                            $stream->pid = $pid;
                            $stream->running = 1;
                            $stream->status = 1;

                            $video = "";
                            $audio = "";

                            if (is_array($streaminfo)) {
                                foreach ($streaminfo['streams'] as $info) {
                                    if ($video == '') {
                                        $video = ($info['codec_type'] == 'video' ? $info['codec_name'] : '');
                                    }
                                    if ($audio == '') {
                                        $audio = ($info['codec_type'] == 'audio' ? $info['codec_name'] : '');
                                    }
                                }
                                $stream->video_codec_name = $video;
                                $stream->audio_codec_name = $audio;
                            }
                        } else {
                            $stream->running = 1;
                            $stream->status = 2;
                            $stream->pid = null;
                        }
                    }
                }
            }
        }
    }
    $stream->save();
}


function generatEginxConfPort($port)
{
    ob_start();
    echo 'user  nginx;
worker_processes  auto;
worker_rlimit_nofile 655350;

events {
    worker_connections  65535;
    use epoll;
        accept_mutex on;
        multi_accept on;
}

http {
        include                   mime.types;
        default_type              application/octet-stream;
        sendfile                  on;
        tcp_nopush                on;
        tcp_nodelay               on;
        reset_timedout_connection on;
        gzip                      off;
        fastcgi_read_timeout      200;
        access_log                off;
        keepalive_timeout         10;
        client_max_body_size      999m;
        send_timeout              120s;
        sendfile_max_chunk        512k;
        lingering_close           off;
	server {
		listen ' . $port . ';
		root /home/fos-streaming/fos/www1/;
		server_tokens off;
		chunked_transfer_encoding off;
		rewrite ^/live/(.*)/(.*)/(.*)$ /stream.php?username=$1&password=$2&stream=$3 break;
		location ~ \.php$ {
		  try_files $uri =404;
		  fastcgi_index index.php;
		  include fastcgi_params;
		  fastcgi_buffering on;
		  fastcgi_buffers 96 32k;
		  fastcgi_buffer_size 32k;
		  fastcgi_max_temp_file_size 0;
		  fastcgi_keep_conn on;
		  fastcgi_param SCRIPT_FILENAME /home/fos-streaming/fos/www1/$fastcgi_script_name;
		  fastcgi_param SCRIPT_NAME $fastcgi_script_name;
		  fastcgi_pass 127.0.0.1:9002;
		}	
	}
	server {
		listen 7777;
		root /home/fos-streaming/fos/www/;
                index index.php index.html index.htm;
                server_tokens off;
                chunked_transfer_encoding off;
		location ~ \.php$ {
                        try_files $uri =404;
                        fastcgi_index index.php;
                        include fastcgi_params;
                        fastcgi_buffering on;
                        fastcgi_buffers 96 32k;
                        fastcgi_buffer_size 32k;
                        fastcgi_max_temp_file_size 0;
                        fastcgi_keep_conn on;
                        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
                        fastcgi_param SCRIPT_NAME $fastcgi_script_name;
                        fastcgi_pass 127.0.0.1:9002;
		}
	}
}';
    $file = '/home/fos-streaming/fos/nginx/conf/nginx.conf';
    $current = ob_get_clean();
    file_put_contents($file, $current);
}
