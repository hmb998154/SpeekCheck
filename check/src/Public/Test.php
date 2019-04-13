<?php 
namespace SpeekCheck\Public;

/**
 * composer测试类
 */
class Test
{

	/**
	 * add
	 *
	 * @param [type] $a [description]
	 * @param [type] $b [description]
	 */
	public static function add($a,$b){
		return $a+$b;
	}

	/**
	 * desc
	 *
	 * @return [type] [description]
	 */
	public static function desc(){
		echo "desc";
	}

}