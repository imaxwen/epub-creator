<?php

/**
 * 使用PHP创建epub电子书
 * @package :
 * @author  :  wendoscoo@zcom
 */

class Epub {

	const     VERSION           = 1.0;
	const     AUTHOR            = 'wendoscoo';
	const     EMIAL             = 'wendosyi@gmail.com';

	protected $mimetype         = "application/epub+zip";
	public    $epubRootPath     = "./";							// 创建打包epub文件系统的工作目录
	public    $opfFile          = 'content.opf';
	public    $ncxFile          = 'toc.ncx';
	public    $imgDir           = 'images';
	public    $cssDir           = 'style';
	public    $oebpsDir         = 'OEBPS';
	private   $metaDir          = 'META-INF';
	private   $containerFile    = 'container.xml';
	private   $mimeFile         = 'mimetype';
	protected $errorMsg         = '';


	/**
	 * 初始化Epub文件系统和核心文件
	 */
	public function init()
	{
		// 初始化文件系统参数
		$metaDir       = $this->epubRootPath . $this->metaDir;
		$oebpsDir      = $this->epubRootPath . $this->oebpsDir;
		$mimeFile      = $this->epubRootPath . $this->mimeFile;
		$imgDir        = $oebpsDir .'/'. $this->imgDir;
		$cssDir        = $oebpsDir .'/'. $this->cssDir;

		if(  ! file_exists( $this->epubRootPath ) )
		{
			if ( ! @mkdir( $this->epubRootPath, 0777 ) )
			{
				$this->logError("错误：权限不够,无法创建'$this->epubRootPath'临时目录。");
				return FALSE;
			}
		}

		// 创建文件系统
		if( ! file_exists($metaDir) )
		{
			mkdir( $metaDir , 0777 ) or $this->logError("错误：权限不够,无法创建META-INF目录。");
		}
		file_exists($oebpsDir) or mkdir( $oebpsDir ,0777 );
		file_exists($imgDir) or mkdir( $imgDir ,0777 ) ;
		file_exists($cssDir) or mkdir( $cssDir ,0777 ) ;
		file_put_contents( $mimeFile , $this->mimetype) or $this->logError("错误：{$this->mimeFile}文件写入失败。");

		$this->createContainer();
	}

	/**
	 * 记录错误信息
	 * @param $msg
	 * @return bool
	 */
	public function logError( $msg )
	{
		if( $msg && is_string($msg) )
		{
			$this->errorMsg .= $msg."<br />\n";
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}

	/**
	 * 如果打包失败 返回记录的错误信息 终止程序运行
	 * @param $msg
	 * @return string
	 */
	public function showError()
	{
		return $this->errorMsg;
	}

	/**
	 * @param string $prefix 为Epub书籍创建唯一的uuid标示符
	 * @return string
	 */
	protected function uuid( $prefix ='' )
	{
		$chars = md5( uniqid(mt_rand() , true ) );

		$uuid  = substr( $chars, 0,8 ) . '-';
		$uuid .= substr( $chars , 8,4 ) . '-';
		$uuid .= substr( $chars , 12,4) . '-';
		$uuid .= substr( $chars , 16,4) . '-';
		$uuid .= substr( $chars , 20,12);

		return $prefix . $uuid;
	}

	/**
	 * 创建META-INF/container.xml文件
	 */
	private function createContainer()
	{
		$this->containerFile = $this->epubRootPath.$this->metaDir .'/'. $this->containerFile;
		file_put_contents( $this->containerFile, $this->container() );
	}

	/**
	 * 设置container.xml的内容
	 * @return string
	 */
	private function container()
	{
		$content  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
		$content .= "<container version=\"1.0\" xmlns=\"urn:oasis:names:tc:opendocument:xmlns:container\">\n";
		$content .= "\t<rootfiles>\n";
		$content .= "\t\t<rootfile full-path=\"{$this->oebpsDir}/{$this->opfFile}\" media-type=\"application/oebps-package+xml\"/>\n";
		$content .= "\t</rootfiles>\n";
		$content .= "</container>";

		return $content;
	}


	function __destruct()
	{
		unset( $this->epubRootPath , $this->epubFileName , $this->opfFile , $this->oebpsDir, $this->ncxFile );
	}


}// EOF Epub class



?>