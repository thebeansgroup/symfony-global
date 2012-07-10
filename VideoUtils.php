<?php

/**
 * Provides utility methods for handling videos. This depends on ffmpeg-php from
 * http://ffmpeg-php.sourceforge.net/
 * 
 * @author al
 */
class VideoUtils
{

  /**
   * Initialises the class by loading the ffmpeg-php extension.
   */
  public function __construct($path)
  {
    if (!is_file($path) || !is_readable($path))
    {
      throw new InvalidArgumentException("$path is not a file or is not readable");
    }

    if (!extension_loaded('ffmpeg'))
    {
      throw new RuntimeException("Error, ffmpeg.so extension isn't loaded");
    }

    $this->video = new ffmpeg_movie($path, false);
  }

  /**
   * Magic getter. If the requested method is a method of ffmpeg_movie, 
   * the result of executing that method is returned
   */
  public function __call($name, $arguments)
  {
    if (method_exists($this->video, $name))
    {
      return call_user_func(array($this->video, $name), $arguments);
    }
  }

  /**
   * Writes a frame to a file. The frame can be scaled, cropped and/or watermarked.
   * 
   * @param string $dest The path to write the frame to 
   * @param int $seconds The number of seconds into the video to capture a frame
   * @param int $width Scale the width of the captured frame to this value. The aspect
   * ratio will be maintained.
   * @param int $height If set, the captured image will first be scaled to $width or
   * $height, and then centre-cropped so its final dimensions are $width x $height
   * @param string $watermark Path to a watermark to apply
   * @param int $watermarkOpacity Opacity to apply to the watermarked image
   * @param int $quality The quality of the outputted jpeg
   * 
   * @return boolean
   */
  public function captureFrame($dest, $seconds, $width='', $height='', $watermark='', $watermarkOpacity=100, $quality=75)
  {
    if (empty($dest))
    {
      throw new InvalidArgumentException('$dest cannot be empty');
    }

    if (file_exists($dest) && (filesize($dest) > 0))
    {
      throw new InvalidArgumentException("$dest already exists");
    }

    if ($seconds <= 0)
    {
      throw new InvalidArgumentException("The seconds parameter must be >= 0. $seconds given.");
    }

    // get scaled frame
    $frame = ($width || $height) ? $this->getScaledFrame($seconds, $width, $height) :
            $this->getNearestKeyFrame($seconds);

    if (!is_resource($frame))
    {
      throw new RuntimeException("Error getting frame for parameters: " . implode(', ', func_get_args()));
    }

    // crop the final image
    $frameWidth = imagesx($frame);
    $frameHeight = imagesy($frame);

    if ($width < $frameWidth || $height < $frameHeight)
    {
      $cropX = round(($frameWidth - $width) / 2);
      $cropY = round(($frameHeight - $height) / 2);

      $canvas = imagecreatetruecolor($width, $height);
      imagecopy($canvas, $frame, 0, 0, $cropX, $cropY, $frameWidth, $frameHeight);

      $frame = $canvas;
    }

    // watermark if necessary
    if ($watermark)
    {
      if (!is_file($watermark) || !is_readable($watermark))
      {
        throw new InvalidArgumentException("Watermark file doesn't exist or is not writeable. Watermark path was $watermark");
      }

      if (!$watermarkImage = imagecreatefrompng($watermark))
      {
        throw new InvalidArgumentException("Error creating png watermark from $watermark");
      }

      $watermarkWidth = imagesx($watermarkImage);
      $watermarkHeight = imagesy($watermarkImage);

      $frameWidth = imagesx($frame);
      $frameHeight = imagesy($frame);

      $watermarkX = round(($frameWidth - $watermarkWidth) / 2);
      $watermarkY = round(($frameHeight - $watermarkHeight) / 2);

      imagecopymerge($frame, $watermark, $watermarkX, $watermarkY, 0, 0, $watermarkWidth, $watermarkHeight, $watermarkOpacity);
    }

    // write the frame to the output file
    imagejpeg($frame, $dest, $quality);
  }

  /**
   * Returns a key frame of the video scaled so either its width == $width
   * or height == $height
   * 
   * @param int $seconds The number of seconds into the video to capture a 
   * key frame
   * @param int $width The width to scale the frame to. The aspect ratio will
   * be maintained
   * @param int $height The height to scale the frame to. The aspect will be
   * maintained.
   * @return resource
   */
  public function getScaledFrame($seconds, $width='', $height='')
  {
    if (!$width && !$height)
    {
      throw new InvalidArgumentException('One of $width or $height must be set');
    }

    $frame = $this->getNearestKeyFrame($seconds);

    // resize the frame
    $aspect = $frame->getWidth() / $frame->getHeight();

    // scale the image up or down accordingly
    if ($width)
    {
      $scaledHeight = ($frame->getWidth() >= $width) ? (1 / $aspect) * $width : $aspect * $width;
      $scaledWidth = $width;
    }

    // only scale on the height if the parameter is set and it is the
    // best dimension to use
    if ($height && $height > $width)
    {
      $scaledWidth = ($frame->getHeight() >= $height) ? (1 / $aspect) * $height : $aspect * $height;
      $scaledHeight = $height;
    }

    $scaledWidth = round($scaledWidth);
    $scaledHeight = round($scaledHeight);

    // resize the frame
    $resizedFrame = imagecreatetruecolor($scaledWidth, $scaledHeight);
    imagecopyresampled($resizedFrame, $frame->toGDImage(), 0, 0, 0, 0, $scaledWidth, $scaledHeight,
            $frame->getWidth(), $frame->getHeight());

    return $resizedFrame;
  }

  /**
   * Returns the first key frame following position $seconds
   * 
   * @param int $seconds The number of seconds into the video to return a 
   * key frame from
   * @return ffmpeg_frame
   */
  public function getNearestKeyFrame($seconds)
  {
    $frame = round($this->video->getFrameRate() * $seconds);

    try
    {
      // get the specified frame
      $this->video->getFrame($frame);
    }
    catch (Exception $e)
    {
      error_log($e);
      throw new RuntimeException("Error getting frame $frame");
    }

    // return the next key frame
    if (!$keyFrame = $this->video->getNextKeyFrame())
    {
      throw new RuntimeException("Error returning key frame $seconds seconds into the video");
    }

    return $keyFrame;
  }

}