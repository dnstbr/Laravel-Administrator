<?php
namespace Frozennode\Administrator\Fields;

use Frozennode\Administrator\Validator;
use Frozennode\Administrator\Config\ConfigInterface;
use Illuminate\Database\DatabaseManager as DB;
use Frozennode\Administrator\Includes\Multup;
use Illuminate\Support\Facades\URL;

class Image extends File {

  /**
   * The specific defaults for the image class
   *
   * @var array
   */
  protected $imageDefaults = array(
    'sizes' => array(),
  );

  /**
   * The specific rules for the image class
   *
   * @var array
   */
  protected $imageRules = array(
    'sizes' => 'array',
    'location' => '',
    'prefix' => 'required',
    'bucket' => 'required'
  );

  /**
   * This static function is used to perform the actual upload and resizing using the Multup class
   *
   * @return array
   */
  public function doUpload()
  {

    //use the multup library to perform the upload
    // $result = Multup::open('file', 'image|max:' . $this->getOption('size_limit') * 1000, $this->getOption('location'),
    //              $this->getOption('naming') === 'random')
    //  ->sizes($this->getOption('sizes'))
    //  ->set_length($this->getOption('length'))
    //  ->upload();

    if(!\Input::hasFile('file') && !\Input::hasFile('upload')) {
       return Response::json('No file was uploaded.');
    }

    $file = \Input::file('file', \Input::get('upload'));
    $path = $file->getRealPath();

    // generate random filename
    $filename = uniqid($this->getOption('prefix')).'.'.$file->guessExtension();

    $image = \PHPImageWorkshop\ImageWorkshop::initFromPath($path);

    // Resize to 600 wide maintain ratio
    $image->resizeInPixel(256, 256, true, 0, 0, 'MM');
    $image->save(dirname($path), basename($path));

    // upload to Amazon S3
    $s3 = \AWS::get('s3');
    $putCommand = $s3->getCommand('PutObject', array(
        'Bucket'      => $this->getOption('bucket'),
        'Key'         => $filename,
        'SourceFile'  => $path,
        'ACL'         => 'public-read',
    ));

    $putCommand->execute();
    $linkToObject = $putCommand->getRequest()->getUrl();

    // remove old file
    \File::delete($path);

    return array('errors' => array(), 'filename' => $linkToObject);
  }

  /**
   * Gets all rules
   *
   * @return array
   */
  public function getRules()
  {
    $rules = parent::getRules();

    return array_merge($rules, $this->imageRules);
  }

  /**
   * Gets all default values
   *
   * @return array
   */
  public function getDefaults()
  {
    $defaults = parent::getDefaults();

    return array_merge($defaults, $this->imageDefaults);
  }
}
