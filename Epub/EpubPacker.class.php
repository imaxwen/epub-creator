<?php

/**
 * 生成EPUB章节内容的chapter_xx.html文件
 * @author: wendoscoo
 */

class EpubPacker extends EpubCore {

	private     $dom;                                   // DOMDocument对象
	public      $workDir = './';                        // 通常是epub文件系统里的OEBPS目录
	private     $ChapterFileName;                       // chapter_xx.html 生成的html文件名
	private     $ChapterOrder;                          // 章节序号
	public      $ChapterPre        = 'chapter_';        // 章节文件前缀
	public      $ChapterTitle      = '';                // 章节标题
	private     $ChapterBody       = '';                // 章节内容 html_entity_decode($body)
	private     $ChapterHtml       = '';                // 最后声称的html内容 用dom->saveHTMLFile()方法会把中文转化为Unicode编码，不建议使用。

	/**
	 * @param string $epubRootPath  创建epub文件的目录
	 * @param string $epubFileName  打包后的epub文件名称
	 */
	function __construct( $epubRootPath = '')
	{
		// 初始化epub打包的工作目录
		if( $epubRootPath && is_string($epubRootPath) )
		{
			$this->epubRootPath = $epubRootPath;
		}

		$this->epubRootPath = rtrim($this->epubRootPath, '/') . '/';

		$this->workDir = $this->epubRootPath.$this->oebpsDir.'/';

		// 书籍的uuid-identifier
		$this->uuid = parent::uuid();
		$this->dom = new DOMDocument('1.0','utf-8');
		$this->dom->formatOutput = $this->formatXML;
		// 初始化Opf DOM
		parent::initOpf();
		parent::initNcx();
	}

	/**
	 * 设置chapter章节的主要内容
	 * @param string $title
	 * @param string $body
	 * @param $playOrder
	 * @param bool $decodeEntity
	 * @return bool
	 */
	public function setData( $title = '', $body = '' ,$playOrder, $decodeEntity = TRUE )
	{
		if( $playOrder < 1 ) { $this->logError('playOrder must start from 1 at least.'); }
		$step1 = $this->setOrder($playOrder);
		$step2 = $this->setTitle($title);
		$step3 = $this->setBody($body,$decodeEntity);

		if( $step1 && $step2 && $step3 )
		{

			return true;
		}
		else { return false; }
	}

	/**
	 * 设置章节序号
	 * @param $chapterOder
	 * @return bool
	 */
	private function setOrder( $chapterOder )
	{
		if( ! is_int( $chapterOder ) )
		{
			return false;
		}
		$this->ChapterOrder = (int)$chapterOder;
		// 设置保存的章节文件名称
		$this->ChapterFileName = $this->workDir.$this->ChapterPre.$this->ChapterOrder . '.html';
		return true;
	}


	/**
	 * 设置章节标题
	 * @param string $chapterTitle
	 * @return bool|void
	 */
	private function setTitle( $chapterTitle = '' )
	{
		if( $chapterTitle && is_string($chapterTitle) )
		{
			$this->ChapterTitle = trim($chapterTitle);
			return true;
		}else{
			return false;
		}
	}

	/**
	 * @param string $chapterBody  	 设置章节主体内容
	 * @param bool $decodeEntity
	 * @return bool
	 */
	private function setBody( $chapterBody = '' , $decodeEntity = TRUE )
	{
		if( ! $chapterBody ||  !is_string($chapterBody) )
		{
			return false;
		}
		$body_encoding = mb_detect_encoding($chapterBody);
		//DOMDocument 只能处理utf-8编码的内容
		if( strtolower($body_encoding) != 'utf-8' )
		{
			$chapterBody = iconv($body_encoding,'utf-8',$chapterBody);
		}

		if( $decodeEntity === TRUE )
		{
			$this->ChapterBody = html_entity_decode($chapterBody,ENT_QUOTES);
		}

		return true;
	}

	/**
	 * make html object data
	 */
	private function make()
	{
		$html       = $this->dom->createElement('html');
		$head       = $this->dom->createElement('head');
		$title      = $this->dom->createElement('title',$this->ChapterTitle);
		$meta       = $this->dom->createElement('meta');

		$body       = $this->dom->createElement('body',"\n");
		$bodytitle  = $this->dom->createElement('h1',$this->ChapterTitle);
		$bodyNode   = $this->dom->createTextNode("\n".$this->ChapterBody."\n");

		$body->appendChild($bodytitle);
		$body->appendChild($bodyNode);

		// declare character.
		$meta->setAttribute('http-equiv','Content-Type');
		$meta->setAttribute('content','text/html; charset=utf-8');
		$html->setAttribute('xmlns','http://www.w3.org/1999/xhtml');

		$head->appendChild($meta);
		$head->appendChild($title);

		$html->appendChild($head);
		$html->appendChild($body);

		return $this->dom->saveXML($html);
	}

	/**
	 * 保存chapter_xx.html文件
	 * @return bool | $ChapterFileName
	 */
	public function saveChapter()
	{
		$this->ChapterHtml = html_entity_decode($this->make(),ENT_QUOTES);

		// 保存html文件
		$fp = fopen($this->ChapterFileName,'w+');
		$bytes = fwrite($fp,$this->ChapterHtml);
		fclose($fp);

		if( $bytes > 0 )
		{
			return basename($this->ChapterFileName);
		}else{
			return false;
		}
	}


	/**
	 * 向opf和ncx文件注册chapter节点
	 */
	public function addChapter()
	{
		$fileName = basename($this->ChapterFileName);
		parent::regChapter($this->ChapterTitle,$fileName,$this->ChapterOrder);
	}

	/**
	 * 查找章节内容里的img标签 返回这些images的src
	 * @param $bodyData
	 * @return array
	 */
	private function findImg( $bodyData)
	{
		$pattern = "/<img(.*)src=\"([^\"]+)\"[^>]+>/isU";
		$matches = array();
		preg_match_all($pattern,$bodyData,$matches);
		$results = $matches[0];
		$images  = array();
		if( count($results) > 0 )
		{
			foreach( $matches[2] as $imageFile )
			{
				empty($imageFile) or array_push($images,$imageFile);
			}
		}

		return $images;
	}


	/**
	 * 替换章节内容中的image图片相对地址
	 * @param $bodyData           提交的章节内容
	 * @param $imgLocationPrefix  源图片的地址
	 * @return mixed|string
	 */
	public function bodyFilter( $bodyData , $imgLocationPrefix )
	{
		$bodyData = html_entity_decode($bodyData,ENT_QUOTES);
		$imgLocationPrefix = rtrim($imgLocationPrefix,'/').'/';
		$images = $this->findImg($bodyData);
		if( !$images )
		{
			return $bodyData;
		}

		// copy image file from imageLocation to epub image directory
		$dest_dir = $this->epubRootPath.$this->oebpsDir.'/'.$this->imgDir;
		foreach( $images as $imageSrc )
		{
			$src_file = $imgLocationPrefix.ltrim($imageSrc,'/');
			$dest_file = $dest_dir.'/'.basename($imageSrc);
			if(  @copy($src_file,$dest_file) )
			{
				parent::regFile($dest_file);
			}else{
				$this->logError('打包图片：'.$src_file.'失败，文件操作权限不够或者源文件不存在！');
			}
		}

		$replace_pat = "/<img([^>\/]+)?src=\"[^\"]+\/(\w+\.\w{3,4})\"([^>\/]+)?\/?>/isU";
		$replacement = '<img${1}src="'.$this->imgDir.'/'.'${2}"${3} />';
		$newData = preg_replace($replace_pat,$replacement,$bodyData);
		return $newData;
	}

}//EOF class Chpater

?>