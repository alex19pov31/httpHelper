<?php

	namespace HTTPHelper;

	/**
	* 
	*/
	class Request
	{
		private $_blocks;
		private $_data = [];
		private $_method;
		private $_checked;

		private $disallowMethods = ['POST', 'GET'];
		
		public function __construct()
		{
			$this->_checked = $this->checkMethod();

			$method = $this->getMethodName();
			$data = $this->parseRequestBody();

			$GLOBALS["_{$method}"] = $data;
			if( !empty($data['files']) ) $_FILES = $data['files'];
		}

		public function parseRequestBody()
		{
			if(!$this->_checked) return false;
			if(!empty($this->_data)) return $this->_data;

			$arBlocks = $this->getBlocks();
			foreach ($arBlocks as $block) {
				if (empty($block)) continue;

				if($this->checkFile($block)) $this->parseFile($block);
				else $this->parseBlock($block);
			}

			return $this->_data;
		}

		private function getBlocks()
		{
			if($this->_blocks) return $this->_blocks;

			$input = file_get_contents('php://input');
			preg_match('/boundary=(.*)$/', $_SERVER['CONTENT_TYPE'], $matches);

			if (empty($matches))
			{
			  parse_str(urldecode($input), $aData);
			  return $aData;
			}
			
			$boundary = $matches[1];
			
			$this->_blocks = preg_split("/-+$boundary/", $input);
			array_pop($this->_blocks);

			return $this->_blocks;
		}

		private function parseBlock($block)
		{
			preg_match('/name=\"([^\"]*)\"[\n|\r]+([^\n\r].*)?\r$/s', $block, $matches);
			$this->_data[$matches[1]] = $matches[2];
		}

		private function parseFile($block)
		{
			preg_match_all("/name=\"([^\"]*)\";\s*filename=\"([^\"]*)\".+Content-Type:\s*([\w-]+\/[\w-]+)[\n|\r]*([^\n\r].*)?/s", $block, $matches);
			$filePath = $this->saveFile($matches[4][0]);
			$fileSize = filesize($filePath);
			$this->_data['files'][] = array(
				'type' => $matches[3][0],
				'name' => $matches[2][0],
				'tmp_name' => $filePath,
				//'content' => $matches[3],
				'size' => $fileSize
			);
		}

		private function saveFile($content)
		{
			$tmpFile = tempnam(sys_get_temp_dir(), '');
			file_put_contents($tmpFile, $content);

			return $tmpFile;
		}

		private function checkFile($block)
		{
			return strpos($block, 'Content-Type:') !== false;
		}

		private function checkMethod()
		{
			$method = $this->getMethodName();
			if( in_array($method, $this->disallowMethods) ) return false;

			$input = file_get_contents('php://input');
			preg_match('/boundary=(.*)$/', $_SERVER['CONTENT_TYPE'], $matches);

			if ( empty($matches) ) return false;
			return true;
		}

		private function getMethodName()
		{
			if($this->_method) return $this->_method;

			$this->_method = $_SERVER['REQUEST_METHOD'];
			return $this->_method;
		}
	}