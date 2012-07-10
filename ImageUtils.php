<?php
/**
 * Provides utility methods for working with images.
 *
 * @author al
 */
class ImageUtils
{
  /**
   * @var Adaptive resizing will resize the image to get as close as possible to the
   * desired dimensions, then crop the image down to the proper size from the center.
   */
  const ADAPTIVE_RESIZE = 'adaptive';

  /**
   * @var Normal resizing will make sure the width is no greater than the provided
   * width and the same for the height.
   */
  const NORMAL_RESIZE = 'normal';

  /**
   * @var Percentage resizing will scale the whole image by a single percentage
   */
  const PERCENTAGE_RESIZE = 'percentage';

  /**
   * Creates a thumbnail. Different kinds of resizing are supported.
   *
   * @param string $inputName Path to an image to resize
   * @param string $outputName Path to save the image to, including file name
   * @param int $width
   * @param int $height
   * @param string $resizeType The type of resizing to use. Pass in one of the *_RESIZE
   * class constants. The default is ADAPTIVE_RESIZE.
   */
  public static function createThumbnail($inputName, $outputName, $width, $height='', $resizeType='')
  {
    if (is_dir($outputName))
    {
      throw new InvalidArgumentException('The $outputName parameter must include a file name. ' .
              'It cannot be a directory name.');
    }

    // make sure the output path is writable
    if (!is_writable(dirname($outputName)) || !is_dir(dirname($outputName)))
    {
      throw new InvalidArgumentException(dirname($outputName) . " is not a writable directory");
    }

    // this will throw an exception if the file can't be opened or read
    $thumb = PhpThumbFactory::create($inputName);

    // select the right resize method
    switch($resizeType)
    {
      case self::NORMAL_RESIZE:
        $method = 'resize';
        break;
      case self::PERCENTAGE_RESIZE:
        $method = 'resizePercent';
        break;
      case self::ADAPTIVE_RESIZE:
        $method = 'adaptiveResize';
        break;
      case '':
        $method = 'adaptiveResize';
        break;
      default:
        throw new InvalidArgumentException("$resizeType is not a valid resize type");
    }

    // percentage resizing only uses a single parameter
    if ($resizeType == self::PERCENTAGE_RESIZE)
    {
      $thumb->$method($width);
    }
    else
    {
      $thumb->$method($width, $height);
    }

    // now save the image
    return $thumb->save($outputName);
  }

  /**
   * Returns a boolean depending on whether the dimensions of the specified image match the
   * given width and height parameters.
   *
   * @param string $image Path to an image file
   * @param int $width The width to test the image against
   * @param int $height The height to test the image against
   * @return bool
   */
  public static function imageMatchesDimensions($image, $width, $height)
  {
    if (!file_exists($image) || !is_readable($image))
    {
      throw new InvalidParameterException("Error, $image is not a file or is not readable");
    }

    $data = getimagesize($image);

    return (($data[0] == $width) && ($data[1] == $height));
  }

  /**
   * Returns a boolean depending on whether the dimensions of the specified image match the
   * given width parameter.
   *
   * @param string $image Path to an image file
   * @param int $width The width to test the image against
   * @return bool
   */
  public static function imageMatchesWidth($image, $width)
  {
    if (!file_exists($image) || !is_readable($image))
    {
      throw new InvalidParameterException("Error, $image is not a file or is not readable");
    }

    $data = getimagesize($image);

    return ($data[0] == $width);
  }

  /*
   * This will return the width of an image and keep its aspect ratio base on a new height
   *
   * @var string $image path to the original image
   * @var int $desiredHeight
   * @return int
   *
  */
  public static function resizeToHeight($image, $desiredHeight)
  {
    if (is_dir($image))
    {
      throw new InvalidArgumentException("The $image parameter must include a file name. 
              It cannot be a directory name.");
    }

    list($width, $height)  = getimagesize($image);
    $ratio = $desiredHeight / $height;

    return $width * $ratio;
  }

  /*
   * This will return the height of an image and keep its aspect ratio based on a new width
   *
   * @var string $image path to the original image
   * @var int $desiredHeight
   * @return int
   *
  */
  public static function resizeToWidth($image, $desiredWidth)
  {
    if (is_dir($image))
    {
      throw new InvalidArgumentException("The $image parameter must include a file name.
              It cannot be a directory name.");
    }
    list($width, $height)  = getimagesize($image);
    $ratio = $desiredWidth / $width;

    return $height * $ratio;
  }
}
