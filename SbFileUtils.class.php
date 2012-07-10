<?php
/**
 * This class contains utility methods that can be used for any general code
 */
class SbFileUtils
{
  /**
   * Creates a file with a random name in the specified directory with
   * the given extension. Makes sure to avoid collisions.
   *
   * @param string $dir Diretory to touch a new file in
   * @param string $extension Make sure you include a full stop if necessary
   * @return string The path to the randomly named file
   */
  public static function getRandomFileName($dir, $extension='')
  {
    if (!is_dir($dir) || !is_writable($dir))
    {
      throw new InvalidArgumentException("$dir is not a directory or is not writable");
    }

    $dir = rtrim($dir, '/') . '/';

    do
    {
      $file = $dir . md5(rand(1, 50000)) . $extension;
    } while(file_exists($file));

    touch($file);

    return $file;
  }

  /**
   * Creates an empty file in the specified directory. The name of the new file
   * is based on the given text, slugified and adjusted in case of collisions with
   * existing file names.
   *
   * @param string $directory The name of the directory to save the file
   * @param string $text The text, unslugified, to base the file name on
   * @param string $extension An extension for the generated file, including full stops
   * @param string $separator A separator to use while slugifying text
   * @return string The name of the file created in the directory
   */
  public static function getFileNameFromSlugifiedText($directory, $text, $extension='', $separator='-')
  {
    $slug = StringUtils::slugify($text, $separator);

    $directory = rtrim($directory, '/') . '/';

    if (empty($slug) || !is_dir($directory) || !is_writable($directory))
    {
      throw new InvalidArgumentException("Text $text is empty when slugified, or directory " .
              "$directory is not a directory or is not writeable");
    }

    $i = 1;

    do
    {
      $file = $directory . $slug . "-$i" . $extension;
      $i++;
    } while (file_exists($file));

    touch($file);

    return $file;
  }

  /**
   * Returns the file extension of the given file name preceded by a separator if there
   * is an extension
   *
   * @param string $filename
   * @param string $separator A separator to use between the file name and extension
   * @return string
   */
  public static function getExtensionWithDot($filename, $separator='.')
  {
    $extension = pathinfo($filename, PATHINFO_EXTENSION);

    $extension = (!empty($extension)) ? $separator . $extension : '';

    return $extension;
  }

  /**
   * Renames a file to a slugified version of $title, after correcting it for
   * collisions. If the file name has already been SEOed for the given title,
   * it won't be renamed again.
   *
   * @param string $fileName The full path to the file to rename.
   * @param string $title A title to base the name of the file on.
   * @return string The base name of the new file name
   */
  public static function seoFileName($fileName, $title)
  {
    if (is_dir($fileName))
    {
      throw new InvalidArgumentException($fileName . ' cannot be a directory!');
    }

    if (empty($title))
    {
      throw new InvalidArgumentException("The title parameter cannot be empty");
    }

    if (!is_writable(dirname($fileName)))
    {
      throw new RuntimeException(dirname($fileName) . " is not writable");
    }

    // first get the seo name of the video
    $seoName = SbFileUtils::getFileNameFromSlugifiedText(dirname($fileName),
            $title, SbFileUtils::getExtensionWithDot($fileName));

    // if the current file name is SEOed already, we don't need to rename it
    if (!StringUtils::stringsSameWithoutTrailingNumbers(
    pathinfo($fileName, PATHINFO_FILENAME),
    pathinfo($seoName, PATHINFO_FILENAME)))
    {
      if (!rename($fileName, $seoName))
      {
        throw new RuntimeException("Error renaming file from " . $fileName .
                " to $seoName");
      }

      return basename($seoName);
    }
    else // we didn't rename the file, so unlink it

    {
      unlink($seoName);
      return basename($fileName);
    }
  }

  /**
   * Deletes a file if it exists
   *
   * @param string $file The file to check the existence of, and delete if it exists
   */
  public static function deleteFileIfExists($file)
  {
    if (file_exists($file))
    {
      if (is_writable($file))
      {
        return unlink($file);
      }
    }
    return false;
  }


  /**
   * getMtimeName get the filename with a mtime attached to it file must be of this type "file.ext"
   *
   * @param $title string the mame of the file you want to transform
   * @param $dir string the directory where this file is
   * @return string
   */

  public static function getMtimeName($title, $dir)
  {
    if (empty($title))
    {
      throw new InvalidArgumentException("The title parameter cannot be empty");
    }

    //$titleArray = split('\.',$title);
    $titleArray = explode('.',$title);

    if (count($titleArray)>2)
    {
      throw new InvalidArgumentException("wrong type of name for the file");
    }

    if (!is_dir($dir) || !is_writable($dir))
    {
      throw new InvalidArgumentException("$dir is not a directory or is not writable");
    }

    $mtimeName = stat($dir . $title);

    return $titleArray[0]."_".$mtimeName['mtime'].".".$titleArray[1];
  }

  /**
   *Function to create a regular file name
   *
   * @param String $fileNameString
   * @param String $illeagalCharReplace
   * @return String
   */
  public static function generateFileNameFromString($fileNameString, $illeagalCharReplace = "")
  {
    $fileNameString = preg_replace('/\s/', "_", $fileNameString);
    $fileNameString = preg_replace('/[^A-Z0-9-_.,]/i', $illeagalCharReplace, $fileNameString);

    return $fileNameString;
  }


}
