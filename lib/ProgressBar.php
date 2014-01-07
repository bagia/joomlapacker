<?php
/**
 * ProgressBar
 *
 * Helper to display a progress bar on the console
 *
 * @author Baudouin
 */

class ProgressBar
{
	private $erase = 0;
	var $value = 0;
	var $size = 20;
	
	function __construct()
	{
	}
	
	function setSize($size)
	{
		$this->size = $size;
	}
	
	function setValue($value)
	{
		if ($value < 0)
		{
			$this->value = 0;
			return;
		}
		
		if ($value > 100)
		{
			$this->value = 100;
			return;
		}
		
		$this->value = $value;
	}
	
	function output($value, $caption = '')
	{
		$this->setValue($value);
		
		$this->computeOutput($caption);
	}
	
	function eraseLine()
	{
		echo "\r";
		for ($i = 0; $i < $this->erase; $i++) {
			echo ' ';
		}
	}
	
	function computeOutput($caption = '')
	{
		$this->eraseLine();
		
		$output = "\r[";
		$n = ceil($this->size * $this->value / 100);
		for( $i = 0; $i < $this->size; $i++)
		{
			if ($i <= $n)
				$output .= '#';
			else
				$output .= ' ';
		}
		$output .= '] ' . $this->value . '%';
		if (!empty($caption)) {
			$output .= ' - '. $caption;
		}
		
		$this->erase = strlen($output);
		echo $output;
	}
}