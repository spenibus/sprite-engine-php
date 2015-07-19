<?php
/*******************************************************************************
sprite-engine
*******************************************************************************/


error_reporting(!E_ALL);


/******************************************************************************/
define('CFG_VERSION',           '20150719-1927');
define('CFG_PATH_IMG',          './img/');
define('CFG_PATH_OUTPUT',       './output/');
define('CFG_PATH_INTERNAL',     './internal/');
define('CFG_PATH_LAST_REBUILD', CFG_PATH_INTERNAL.'last-rebuild.txt');
define('CFG_RESOLUTION_MAX',    1024);




/******************************************************************************/
// return 32bit ARGB color index
function seColor($r=0, $g=0, $b=0, $a=0) {
   return ($a << 24) + ($r << 16) + ($g << 8) + $b;
}




/******************************************************************************/
function seImagesList() {

   $files = array();

   $d = opendir(CFG_PATH_IMG);

   while(false !== $f = readdir($d)) {
      if(is_file(CFG_PATH_IMG.$f) && substr($f,0,1) != '.') {
         $files[$f] = array(
            'file' => $f,
            'mod'  => filemtime(CFG_PATH_IMG.$f),
         );
      }
   }
   closedir($d);

   ksort($files);

   return $files;
}




/******************************************************************************/
function seOpenImage($f) {

   $prop = getimagesize($f);

   $img = null;

   if($prop['mime'] == 'image/jpeg') {
      $img = imagecreatefromjpeg($f);
   }
   elseif($prop['mime'] == 'image/png') {
      $img = imagecreatefrompng($f);
   }
   elseif($prop['mime'] == 'image/gif') {
      $img = imagecreatefromgif($f);
   }

   if($img) {
      return array(
         'img'  => $img,
         'prop' => $prop,
      );
   }

   return false;
}




/******************************************************************************/
function seSpriteBuild($size=0, $imgFiles, $rebuild=false) {

   $file_css    = CFG_PATH_OUTPUT.$size.'.css';
   $file_sprite = CFG_PATH_OUTPUT.$size.'.png';

   // missing cache
   if(!file_exists($file_css) || !file_exists($file_sprite)) {
      $rebuild = true;
   }

   if($rebuild) {

      $css = '';

      // number of images
      $imgCount = count($imgFiles);

      // grid width
      $gridSizeW = ceil(sqrt($imgCount));

      // grid height
      $gridSizeH = ceil($imgCount/$gridSizeW);

      $img = imagecreatetruecolor($gridSizeW * $size, $gridSizeH * $size);

      // save alpha
      imagesavealpha($img, true);

      // enable anti aliasing
      imageantialias($img, true);

      // default background: transparent
      imagealphablending($img, false);
      imagefill($img, 0, 0, seColor(0,0,0,127));
      imagealphablending($img, true);

      reset($imgFiles);

      // insert images into grid
      $imgId = -1;
      for($y=0; $y<$gridSizeH; ++$y) {
         for($x=0; $x<$gridSizeW; ++$x) {

            $imgName = current($imgFiles);
            next($imgFiles);
            $imgName = $imgName['file'];

            // no more source images
            if(!$imgName) {
               break 2;
            }

            // open image
            $imgSrc = seOpenImage(CFG_PATH_IMG.$imgName);

            // resize factor
            if($imgSrc['prop'][0] >= $imgSrc['prop'][1]) {
               $resizeFactor = $size / $imgSrc['prop'][0];
               $wide = true;
            }
            else {
               $resizeFactor = $size / $imgSrc['prop'][1];
               $wide = false;
            }

            $w = round($imgSrc['prop'][0] * $resizeFactor);
            $h = round($imgSrc['prop'][1] * $resizeFactor);

            if($wide) {
               $xOffset = 0;
               $yOffset = round(($size - $h) / 2);
            }
            else {
               $xOffset = round(($size - $w) / 2);
               $yOffset = 0;
            }

            // insert into grid
            imagecopyresampled(
               $img, $imgSrc['img'],
               $x*$size+$xOffset, $y*$size+$yOffset,
               0, 0,
               $w, $h,
               $imgSrc['prop'][0], $imgSrc['prop'][1]
            );
            imagedestroy($imgSrc['img']);

            // associated css
            preg_match('/^(.*)\..*$/siu', $imgName, $cssName);
            $cssName = $cssName[1];
            $css .= "
.se_${size}_${cssName} {
   display:inline-block;
   width:${size}px;
   height:${size}px;
   background-image:url('output/${size}.png');
   background-position:-".($x*$size)."px -".($y*$size)."px;
}";
         }
      }

      imagepng($img, $file_sprite);
      imagedestroy($img);

      file_put_contents($file_css, $css);
   }

   // read from cache
   return file_get_contents($file_css);
}




/******************************************************************************/
if($_GET['css']) {

   $sizes = explode(',', $_GET['css']);

   $css = '/*******************************************************************************
sprite-engine

spenibus.net
https://github.com/spenibus/sprite-engine-php
https://gitlab.com/spenibus/sprite-engine-php

version: '.CFG_VERSION.'
generated: '.gmdate('Y-m-d H:i:s O').'
*******************************************************************************/';

   // get images
   $imgFiles    = seImagesList();
   $lastRebuild = unserialize(file_get_contents(CFG_PATH_LAST_REBUILD));


   // check for change
   $rebuild = false;

   // different count
   if(count($imgFiles) != count($lastRebuild)) {
      $rebuild = true;
   }
   // check source image change
   else {
      foreach($imgFiles as $f=>$file) {
         if($file['mod'] != $lastRebuild[$f]['mod']) {
            $rebuild = true;
            break;
         }
      }
   }


   // build (and rebuild)
   foreach($sizes as $size) {

      $size = (int)$size;

      // invalid size
      if(!($size > 0 && $size <= CFG_RESOLUTION_MAX)) {
         continue;
      }

      // valid size
      // make sprite image and get sprites positions
      $css .= seSpriteBuild($size, $imgFiles, $rebuild);
   }


   // update last rebuild time
   if($rebuild) {
      file_put_contents(CFG_PATH_LAST_REBUILD, serialize($imgFiles));
   }

   header('content-type: text/css');
   exit($css);
}




/******************************************************************************/
exit('<!DOCTYPE html>
<html>
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>sprite-engine</title>
  </head>
  <body>
    <h1>sprite-engine</h1>
    <br /><a href="http://spenibus.net">spenibus.net</a>
    <br /><a href="https://github.com/spenibus/sprite-engine-php">https://github.com/spenibus/sprite-engine-php</a>
    <br /><a href="https://gitlab.com/spenibus/sprite-engine-php">https://gitlab.com/spenibus/sprite-engine-php</a>
    <br />
    <br />version: '.CFG_VERSION.'
  </body>
</html>');
?>