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

		private $disallowMethods = ['POST', 'GET'];
		
		public function __construct()
		{
			if(!$this->checkMethod()) return false;

			$method = $this->getMethodName();
			$data = $this->parseRequestBody();

			$GLOBALS["_{$method}"] = $data;
		}

		public function parseRequestBody()
		{
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

		private function parseBlock()
		{
			preg_match('/name=\"([^\"]*)\"[\n|\r]+([^\n\r].*)?\r$/s', $block, $matches);
			$this->_data[$matches[1]] = $matches[2];
		}

		private function parseFile()
		{
			preg_match("/Content-Type:\s*([\w\/-]+).*/s", $block, $content);  
			preg_match("/name=\"([^\"]*)\".*filename=\"([^\"]*)\".*stream[\n|\r]+([^\n\r].*)?$/s", $block, $matches);
			$this->_data['files'][$matches[1]] = array(
				'type' => $content[1],
				'name' => $matches[2],
				'tmp_name' => $this->saveFile($matches[3]),
				'content' => $matches[3],
				'size' => strlen($matches[3]);
			);
		}

		private function saveFile($content)
		{
			$tmpFile = tempnam(sys_get_temp_dir(), '');
			file_put_contents($tmpFile, $content);

			return $tmpFile;
		}

		private function checkFile()
		{
			return strpos($block, 'Content-Type:') !== false;
		}

		private function checkMethod()
		{
			$method = $this->getMethodName();
			return !in_array($method, $this->disallowMethods);
		}

		private function getMethodName()
		{
			if($this->_method) return $this->_method;

			$this->_method = $_SERVER['REQUEST_METHOD'];
			return $this->_method;
		}
	}