<?php

include_once 'utils.php';
/**
* Generate C++ qt/obd compatible class from MySql table info
*/
class CppQtOdbClass{

	private $className, $attrs;
	
	/**
	* @param $table the table's name
	* @param $info array of stdObject with attrs' info
	*/
	public function __construct($table, $info){
		$this->className = snakeToCamelUC($table);
		$this->attrs = self::normalize($info);
	}

	private function genAttr($attr,$ident){
		$code = "";
		if ($attr->isPK()) {
			$code .= $ident."#pragma db id";
			if ($attr->isInt()) {
				$code .= " auto";
			}
			$code .= "\n";
		}
		$code .= $ident.$attr->type." ".$attr->name.";\n";
		return $code;
	}

	private function genConstructor(){
		return $this->className."(){};\n";
	}

	private function genGetter($attr,$withBody,$ident){
		$code = $ident.$attr->type.' get'.snakeToCamelUC($attr->name).'()';
		if($withBody)
			$code .= "{\n\t".$ident."return ".$attr->name.";\n".$ident."}\n";
		else 
			$code .= ";\n";
		return $code;
	}


	private function genSetter($attr,$withBody,$ident){
		$code = $ident.'void set'.snakeToCamelUC($attr->name).'('.$attr->type.' '.$attr->name.')';
		if($withBody)
			$code .= "{\n\t".$ident."this.".$attr->name.' = '.$attr->name.";\n".$ident."}\n";
		else 
			$code .= ";\n";
		return $code;
	}

	/**
	* @param $info array of stdObject with attrs' mysql info
	* @return an array with Cpp attributes' info objects
	*/
	static function normalize($info){
		$attrs = array();
		foreach ($info as $mysqlAttr) {
			$attrs[] = new CppAttr($mysqlAttr);
		}
		return $attrs;
	}

	static function genIncludes($attrs){
		$includes = array();
		$code = "#include <odb/core.hxx>\n";
		foreach ($attrs as $attr) {
			if(startsWith($attr->type, 'Q')  && !in_array($attr->type, $includes)){
				$includes[] = $attr->type;
				$code .= '#include<'.$attr->type.">\n";
			}
		}
		return $code."\n";
	}

	public function generate($asHeader){
		$code = $this->genIncludes($this->attrs);
		$public = "\tpublic: \n";
		$private = "\tprivate:\n";
		$private.= "\t\t".$this->genConstructor();
		$private.= "\t\tfriend class odb::access;\n\n";
		foreach ($this->attrs as $attr) {
			$private.= $this->genAttr($attr,"\t\t");
			$public .= $this->genGetter($attr,!$asHeader,"\t\t");
			$public .= $this->genSetter($attr,!$asHeader,"\t\t");
			$public .= "\n";
		}
		$code .= "#pragma db object\n";
		$code .= "class ".$this->className."{\n\n";
		$code .= $private."\n";
		$code .= $public."\n";
		$code .= "}";
		return $code;
	}
}


/**
* Class to normalize the info recovered from infoschema
*/
class CppAttr
{
	public $name, $type, $notnull, $extra, $key_type;

	public function __construct($mysqlAttr)
	{
		$this->name = $mysqlAttr->cname;
		$this->type = $this->cppType($mysqlAttr->ctype);
		$this->notnull = $mysqlAttr->cnull == 'YES';
		$this->extra = $mysqlAttr->cextra;
		$this->key_type = $mysqlAttr->ckey;
	}

	public function isPK(){
		return $this->key_type === "PRI";
	}

	public function isInt(){
		return $this->type === "int" || $this->type === "long";
	}

	public function cppType($mysqlType){
		switch (strtolower($mysqlType)) {
			case 'bigint': return 'long';
			case 'binary': return 'byte[]';
			case 'bit': return 'bool';
			case 'char': return 'QString';
			case 'date': return 'QDate';
			case 'datetime': return 'QDate';
			case 'datetime2': return 'QDate';
			case 'decimal': return 'double';
			case 'double': return 'double';
			case 'float': return 'float';
			case 'image': return 'byte[]';
			case 'int': return 'int';
			case 'longtext': return 'QString';
			case 'money': return 'double';
			case 'nchar': return 'QString';
			case 'ntext': return 'QString';
			case 'numeric': return 'Double';
			case 'nvarchar': return 'QString';
			case 'real': return 'double';
			case 'smalldatetime': return 'QDate';
			case 'smallint': return 'int';
			case 'mediumint': return 'int';
			case 'smallmoney': return 'double';
			case 'text': return 'QString';
			case 'time': return 'QDate';
			case 'timestamp': return 'QDate';
			case 'tinyint': return 'bool';
			case 'uniqueidentifier': return 'QString';
			case 'varbinary': return 'byte[]';
			case 'varchar': return 'QString';
			case 'year': return 'int';
			default: return 'UNKNOWN_'.$mysqlType; 
		}
	}
}
