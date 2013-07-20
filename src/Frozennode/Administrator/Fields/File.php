<?php
namespace Frozennode\Administrator\Fields;

use Frozennode\Administrator\Validator;
use Frozennode\Administrator\Config\ConfigInterface;
use Illuminate\Database\DatabaseManager as DB;
use Illuminate\Support\Facades\URL;
use Frozennode\Administrator\Includes\Multup;

class File extends Field {

	/**
	 * The specific defaults for subclasses to override
	 *
	 * @var array
	 */
	protected $defaults = array(
		'naming' => 'random',
		'length' => 32,
		'mimes' => false,
		'size_limit' => 2,
	);

	/**
	 * The specific rules for subclasses to override
	 *
	 * @var array
	 */
	protected $rules = array(
		//'location' => 'required|string|directory',
		'naming' => 'in:keep,random',
		'length' => 'integer|min:0',
		'mimes' => 'string',
	);

	/**
	 * Builds a few basic options
	 */
	public function build()
	{
		parent::build();

		//set the upload url depending on the type of config this is
		$url = $this->validator->getUrlInstance();
		$route = $this->config->getType() === 'settings' ? 'admin_settings_file_upload' : 'admin_file_upload';

		//set the upload url to the proper route
		$this->suppliedOptions['upload_url'] = $url->route($route, array($this->config->getOption('name'), $this->suppliedOptions['field_name']));
	}

	/**
	 * This static function is used to perform the actual upload and resizing using the Multup class
	 *
	 * @return array
	 */
	public function doUpload()
	{
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
}
