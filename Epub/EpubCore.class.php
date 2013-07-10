<?php
/**
 * 创建epub核心文件
 */
class EpubCore extends Epub {

	protected  $uuid       = '';
	public     $formatXML  = TRUE;
	// OPF DOM & Node
	private    $OpfDom     = NULL;
	private    $package    = NULL;
	private    $metadata   = NULL;
	private    $manifest   = NULL;
	private    $spine      = NULL;
	private    $guide      = NULL;
	private    $metaConfig = array(
							'title'       => '',
							'creator'     => '',
							'subject'     => '',
							'description' => '',
							'publisher'   => '',
							'contributor' => '',
							'date'        => '',
							'type'        => '',
							'format'      => '',
							'source'      => '',
							'language'    => 'zh-cn',
							'relation'    => '',
							'coverage'    => '',
							'rights'      => 'zBook.EpubMaker'
							);
	public     $coverPage   = 'cover.html';         // 封面页面名称
	public     $coverPageId = 'coverPage';          // 封面页面ID
	public     $coverImg    = 'cover';              // 封面图片名称
	public     $coverImgId  = 'coverImg';           // 封面图片id
	private    $mediaType   = array(
							'gif'  =>"image/gif",
							'jpg'  =>"image/jpeg",
							'jpeg' =>"image/jpeg",
							'png'  =>"image/png",
							'bmp'  =>"image/bmp",
							'svg'  =>"image/svg+xml",
							'html' =>"application/xhtml+xml",
							'xml'  =>"application/xml",
							'ncx'  =>"application/x-dtbncx+xml",
							'css'  =>"text/css"
							);

	private    $NcxDom      = NULL;
	private    $NcxHead     = NULL;
	private    $NcxWrapper  = NULL;
	private    $NcxNavMap   = NULL;



	/**
	 * 初始化pacakge Dom Node
	 */
	protected function initOpf()
	{
		$this->OpfDom = new DOMDocument('1.0','utf-8');
		$this->OpfDom->formatOutput = $this->formatXML;
		$this->package = $this->OpfDom->createElement('package');

		$this->package->setAttribute('xmlns','http://www.idpf.org/2007/opf');
		$this->package->setAttribute('version','2.0');
		$this->package->setAttribute('unique-identifier','uuid_id');

		// childNodes
		$this->metadata = $this->OpfDom->createElement('metadata');
		$this->manifest = $this->OpfDom->createElement('manifest');
		$this->spine    = $this->OpfDom->createElement('spine');
		$this->guide    = $this->OpfDom->createElement('guide');

		// add attributes for metadata dom
		$this->metadata->setAttribute('xmlns:xsi','http://www.w3.org/2001/XMLSchema-instance');
		$this->metadata->setAttribute('xmlns:opf','http://www.idpf.org/2007/opf');
		$this->metadata->setAttribute('xmlns:dcterms','http://purl.org/dc/terms/');
		$this->metadata->setAttribute('xmlns:calibre','http://calibre.kovidgoyal.net/2009/metadata');
		$this->metadata->setAttribute('xmlns:dc','http://purl.org/dc/elements/1.1/');

		// add attributes for spine dom
		$this->spine->setAttribute('toc','ncx');
	}


	/**
	 * 为metadata创建epub元数据 [epub书籍主信息存储]
	 * @param array $configData
	 */
	public function setBookInfo( $configData = array() )
	{
		// 将用户数据传入并过滤
		foreach( $configData as $tagName => $tagValue )
		{
			if( isset( $this->metaConfig[$tagName] ) )
			{
				if( empty( $tagValue ) ){ CONTINUE; }
				if( is_string($tagValue) )
					$this->metaConfig[$tagName] = $tagValue;
				else
					$this->logError('metaConfig参数类型不合法，请使用字符串传参。');
			}else{
				unset($configData[$tagName]);
			}
		}

		if( !$this->metaConfig['date'] ){ $this->metaConfig['date'] = date('Y-m-d'); }

		// 为metadata dom创建元数据节点
		foreach( $this->metaConfig as $tag => $value )
		{
			if( in_array($tag,array('title','creator','description','publisher','subject')) )
			{
				$tag = 'dc:'.$tag;
				$tagNode  = $this->OpfDom->createElement($tag);
				$tagValue = $this->OpfDom->createCDATASection($value);
				$tagNode->appendChild($tagValue);
			}else{
				$tag = 'dc:'.$tag;
				$tagNode = $this->OpfDom->createElement($tag,$value);
			}
			$this->metadata->appendChild($tagNode);
		}

		$this->setIdent();
		$this->setXmeta('cover',$this->coverImgId);
		$this->setXmeta('zBook version',EPUB::VERSION);
	}


	/**
	 * 为metadata创建identifier节点
	 */
	private function setIdent()
	{
		$ident = $this->OpfDom->createElement('dc:identifier',$this->uuid);
		$ident->setAttribute('id','uuid_id');
		$ident->setAttribute('opf:scheme','uuid');
		$this->metadata->appendChild($ident);
	}


	/**
	 * @param string $name
	 * @param string $content  为metadata创建xmeta节点
	 */
	private function setXmeta( $name = '' , $content = '' )
	{
		$xMeta = $this->OpfDom->createElement('meta');
		$xMeta->setAttribute('name',$name);
		$xMeta->setAttribute('content',$content);
		$this->metadata->appendChild($xMeta);
	}


	/**
	 * 添加封面图片 制作封面html文件
	 * @param string $coverImage
	 * @return bool
	 */
	public function makeCover( $coverImage = '' )
	{
		if( !$coverImage || !is_string($coverImage) )
		{
			$this->logError('封面图片打包失败，必须传入图片的完整路径。');
		}

		$fileInfo  = explode('.',basename( $coverImage ));
		$file_ext  = end($fileInfo);
		$dest_file = $this->epubRootPath .$this->oebpsDir.'/' . $this->imgDir .'/'.$this->coverImg.'.'.$file_ext;
		if( @!copy($coverImage, $dest_file ))
		{
			$this->logError('Failed to copy cover image'.$coverImage.' to '.$dest_file);
			return false;
		}

		$this->coverImg = $this->imgDir .'/'. basename($dest_file);

		$createCover = $this->createCoverPage();

		if( $createCover === false )
		{
			$this->logError('封面页面打包失败，请检查！');
			return;
		}
		// 为manifest添加cover封面和图片
		$this->regCover( $file_ext );
	}


	/**
	 * @return bool 打包封面图片后生成封面页面cover.html
	 */
	private function createCoverPage()
	{
		$coverDom = new DOMDocument('1.0','utf-8');
		$coverDom->formatOutput = $this->formatXML;

		$html  = $coverDom->createElement('html');
		$head  = $coverDom->createElement('head');
		$title = $coverDom->createElement('title',$this->coverPageId);
		$body  = $coverDom->createElement('body');
		$div   = $coverDom->createElement('div');
		$img   = $coverDom->createElement('img');

		$html->setAttribute('xmlns','http://www.w3.org/1999/xhtml');
		$html->setAttribute('xml:lang',$this->metaConfig['language']);
		$img->setAttribute('src',$this->coverImg);
		$img->setAttribute('alt',$this->metaConfig['title']);
		$img->setAttribute('style','height:100%');

		$div->appendChild($img);
		$body->appendChild($div);
		$head->appendChild($title);
		$html->appendChild($head);
		$html->appendChild($body);

		$coverPageContent = $coverDom->saveXML($html);
		$fullPath    = $this->epubRootPath.$this->oebpsDir.'/'.$this->coverPage;

		$bytes = file_put_contents($fullPath,$coverPageContent);
		if( $bytes > 0 )
		{
			// add spine itemRef for opf Dom
			$this->addSpine($this->coverPageId);
			$this->addNavPoint($this->coverPageId,'封面',0,$this->coverPage);
			unset($coverDom);
			return true;
		}else{
			return false;
		}
	}


	/**
	 * 为manifest Node 添加封面图和页面的item Node
	 * @param string $file_ext
	 */
	private function regCover( $file_ext = '')
	{
		$coverImg  = $this->OpfDom->createElement('item');
		$coverPage = $this->OpfDom->createElement('item');

		$coverImg->setAttribute('id',$this->coverImgId);
		$coverPage->setAttribute('id',$this->coverPageId);
		$coverImg->setAttribute('href',$this->coverImg);
		$coverPage->setAttribute('href',$this->coverPage);
		$coverImg->setAttribute('media-type',$this->mediaType[$file_ext]);
		$coverPage->setAttribute('media-type',$this->mediaType['html']);

		$this->manifest->appendChild($coverImg);
		$this->manifest->appendChild($coverPage);
		$this->regNcx();
	}


	/**
	 * @ 为manifest Node 添加Ncx item node
	 */
	private function regNcx()
	{
		$ncxItem = $this->OpfDom->createElement('item');
		$ncxItem->setAttribute('id','ncx');
		$ncxItem->setAttribute('href',$this->ncxFile);
		$ncxItem->setAttribute('media-type',$this->mediaType['ncx']);
		$this->manifest->appendChild($ncxItem);
	}


	/**
	 * 为manifest注册chapter文件列表
	 * @param $chapterTitle
	 * @param string $chapterFileName
	 * @param $chapterOrder
	 */
	protected function regChapter( $chapterTitle,$chapterFileName = '' , $chapterOrder )
	{
		if( ! $chapterFileName || !is_string($chapterFileName) )
		{
			$this->logError('注册章节文件'.$chapterFileName.'失败，参数不合法！');
		}
		$chapterId = 'chapter'.$chapterOrder;
		$chapterItem = $this->OpfDom->createElement('item');
		$chapterItem->setAttribute('id',$chapterId);
		$chapterItem->setAttribute('href',$chapterFileName);
		$chapterItem->setAttribute('media-type',$this->mediaType['html']);

		$this->manifest->appendChild($chapterItem);
		$this->addSpine($chapterId);
		$this->addNavPoint($chapterId,$chapterTitle,$chapterOrder,$chapterFileName);
	}


	protected function regFile( $fileName )
	{
		$fileName = basename($fileName);
		$fileInfo = explode('.',$fileName);
		$file_ext = end($fileInfo);
		if( isset( $this->mediaType[$file_ext] ) )
		{
			switch( $file_ext )
			{
				case 'jpg':
				case 'jpeg':
				case 'gif':
				case 'png':
				case 'bmp':
					$href = $this->imgDir.'/'.$fileName;
					break;
				case 'css':
					$href = $this->cssDir.'/'.$fileName;
					break;
				default:
					$href = $fileName;
					break;
			}
			$mediatype = $this->mediaType[$file_ext];
		}else{
			$href = $fileName;
			$mediatype = 'application/octet-stream';
		}
		$Item = $this->OpfDom->createElement('item');
		$Item->setAttribute('id',$fileName);
		$Item->setAttribute('href',$href);
		$Item->setAttribute('media-type',$mediatype);
		$this->manifest->appendChild($Item);
	}


	/**
	 * @param $idRef  为opf文件添加spine [此Node提供epub线性阅读顺序]
	 */
	private function addSpine( $idRef )
	{
		$item = $this->OpfDom->createElement('itemref');
		$item->setAttribute('idref',$idRef);
		$this->spine->appendChild($item);
	}


	/**
	 * 为Opf 文件添加guide Node
	 */
	private function addGuide()
	{
		$reference = $this->OpfDom->createElement('reference');
		$reference->setAttribute('href',$this->coverPage);
		$reference->setAttribute('type','cover');
		$reference->setAttribute('title','封面');

		$this->guide->appendChild($reference);
	}

	//--------------------------------------------- Ncx stuff -------------------------------------------------

	/**
	 * 初始化Ncx DOM object
	 */
	protected function initNcx()
	{
		$this->NcxDom = new DOMDocument('1.0','utf-8');
		$this->NcxDom->formatOutput = $this->formatXML;
		// ncx tag Node
		$this->NcxWrapper = $this->NcxDom->createElement('ncx');
		$this->NcxWrapper->setAttribute('xmlns','http://www.daisy.org/z3986/2005/ncx/');
		$this->NcxWrapper->setAttribute('version','2005-1');
		// ncx head tag Node
		$this->NcxHead = $this->NcxDom->createElement('head');
		$this->addNcxMeta('dtb:uid',$this->uuid);
		$this->addNcxMeta('dtb:depth','2');
		$this->addNcxMeta('dtb:generator',$this->metaConfig['rights']);
		$this->addNcxMeta('dtb:totalPageCount','0');
		$this->addNcxMeta('dtb:maxPageNumber','0');
		// create base node
		$this->NcxWrapper->appendChild($this->NcxHead);
		$this->regNcxTitle($this->metaConfig['title']);
		$this->regNcxAuthor($this->metaConfig['creator']);
		$this->NcxNavMap = $this->NcxDom->createElement('navMap');
	}


	/**
	 * 为NCX的head制作meta Node
	 * @param string $name
	 * @param string $content
	 * @return Meta Node
	 */
	private function addNcxMeta($name='', $content = '')
	{
		$meta = $this->NcxDom->createElement('meta');
		$meta->setAttribute('content',$content);
		$meta->setAttribute('name',$name);
		$this->NcxHead->appendChild($meta);
	}


	/**
	 * 设置Ncx Dom 的docTitle
	 * @param $title
	 */
	private function regNcxTitle( $title )
	{
		$titleNode = $this->NcxDom->createElement('docTitle');
		$textNode  = $this->NcxDom->createElement('text');
		$content   = $this->NcxDom->createCDATASection($title);

		$textNode->appendChild($content);
		$titleNode->appendChild($textNode);
		$this->NcxWrapper->appendChild($titleNode);
	}


	/**
	 * 设置Ncx Dom的docAuthor
	 * @param $author
	 */
	private function regNcxAuthor( $author )
	{
		$authorNode = $this->NcxDom->createElement('docAuthor');
		$textNode  = $this->NcxDom->createElement('text');
		$content   = $this->NcxDom->createCDATASection($author);

		$textNode->appendChild($content);
		$authorNode->appendChild($textNode);
		$this->NcxWrapper->appendChild($authorNode);
	}


	/**
	 * 为NavMap添加目录节点
	 * @param $point_id     章节或者封面的id
	 * @param $NavTitle     章节的标题
	 * @param $playOrder    章节的阅读顺序
	 * @param $fileName     章节的文件名称[全路径]
	 */
	private function addNavPoint( $point_id , $NavTitle, $playOrder, $fileName )
	{
		$navPoint = $this->NcxDom->createElement('navPoint');
		$navPoint->setAttribute('class','chapter');
		$navPoint->setAttribute('id',$point_id);
		$navPoint->setAttribute('playOrder',$playOrder);

		$navLabel = $this->NcxDom->createElement('navLabel');
		$textNode = $this->NcxDom->createElement('text');
		$text     = $this->NcxDom->createCDATASection($NavTitle);
		$contentNode = $this->NcxDom->createElement('content');
		$contentNode->setAttribute('src',$fileName);

		$textNode->appendChild($text);
		$navLabel->appendChild($textNode);

		$navPoint->appendChild($navLabel);
		$navPoint->appendChild($contentNode);

		$this->NcxNavMap->appendChild($navPoint);
	}


	/**
	 * @return mixed 生成ncx文件，成功返回文件大小
	 */
	private function packNcx()
	{
		$this->NcxWrapper->appendChild($this->NcxNavMap);
		$this->NcxDom->appendChild($this->NcxWrapper);
		$packPath = $this->epubRootPath.$this->oebpsDir.'/'.$this->ncxFile;
		$bytes = $this->NcxDom->save($packPath);
		if( $bytes > 0 )
		{
			return $bytes;
		}else{
			$this->logError($packPath.'文件打包失败！');
		}
	}


	/**
	 * @return mixed 生成opf文件，成功返回文件大小
	 */
	private function packOpf()
	{
		$this->package->appendChild($this->metadata);
		$this->package->appendChild($this->manifest);
		$this->package->appendChild($this->spine);
		$this->addGuide();
		$this->package->appendChild($this->guide);

		$this->OpfDom->appendChild($this->package);
		$packFile = $this->epubRootPath.$this->oebpsDir.'/'.$this->opfFile;

		$bytes = $this->OpfDom->save($packFile);
		if( $bytes > 0 )
		{
			return $bytes;
		}else{
			$this->logError($packFile.'文件打包失败！');
		}
	}


	/**
	 * 保存并打包书籍
	 * @param string $savePath
	 * @param $epubFileName
	 * @param bool $delete_rootDir  是否删除打包目录
	 * @return bool
	 */
	public function saveBook( $savePath = './', $epubFileName, $delete_rootDir = TRUE )
	{
		// 初始化打包后的epub文件名称
		if( $epubFileName && is_string( $epubFileName ) )
		{
			$fileName = $epubFileName;
		}else{
			$fileName = date('YmdHis').'.epub';
		}

		$Opf_bytes = $this->packOpf();
		$Ncx_bytes = $this->packNcx();

		if( ! $Opf_bytes || !$Ncx_bytes )
		{
			return FALSE;
		}

		$result = $this->zipFile( $savePath ,$fileName);

		if( $delete_rootDir === TRUE )
		{
			$rootpath = rtrim($this->epubRootPath,'/');
//			$this->rrmdir($rootpath);
		}

		return $result;
	}

	function rrmdir($dir) {
		foreach(glob($dir . '/*') as $file) {
			if(is_dir($file))
				$this->rrmdir($file);
			else
				unlink($file);
		}
		rmdir($dir);
	}


	/**
	 * 将生成的epub文件按照zip标准压缩
	 * @param $saveDir
	 * @param $fileName
	 * @return bool
	 */
	private function zipFile( $saveDir ,$fileName )
	{
		$zip = new Zip();
		$zip->read_dir($this->epubRootPath);
		$fullPath = $saveDir . $fileName;
		return $zip->archive($fullPath);
	}

}

?>