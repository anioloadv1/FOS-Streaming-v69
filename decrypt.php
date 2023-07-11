<?php


$key = '11ad69d2c84f41dbb520abfe38b19fe7';
$encrypted = file_get_contents("Segment.m4v");
//var_dump($encrypted);
//$rawData = hex2bin($encrypted);
$decrypted = openssl_decrypt($encrypted, 'aes-128-ctr', $key,  OPENSSL_RAW_DATA | OPENSSL_DONT_ZERO_PAD_KEY | OPENSSL_ZERO_PADDING);
$data = unpadZero($decrypted);
//var_dump($data);

file_put_contents("Segment_clear.m4v",$decrypted);
//var_dump($decrypted);








function padZero($data, $blocksize = 16)
{
    $pad = $blocksize - (strlen($data) % $blocksize);
    return $data . str_repeat("\0", $pad);
}

function unpadZero($data)
{
    return rtrim($data, "\0");
}

?>