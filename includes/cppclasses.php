<?php

include_once 'includes/utils.php';

include_once 'includes/qtclasses.php';
/**
* Generate C++ qt/obd compatible class from MySql table info
*/
class CppQtOdbClass{

	private $className, $tableName, $attrs;
	
	/**
	* @param $table the table's name
	* @param $attrs array of stdObject with attrs' info
	*/
	public function __construct($table, $attrs, $rels){
		$this->className = snakeToCamelUC($table);
		$this->tableName = strtolower($table);
		$this->attrs = self::normalizeAttrs($attrs);
		$this->rels = self::normalizeRels($rels);
		if ($this->doesntHaveId()) {
			$this->addDefaultId();
		}
	}

	private function genAttr($attr,$ident){
		$code = "";
		$def = $ident.$attr->type." ".$attr->name_.";";
		if ($attr->comment != "") {
			$def .= " // ".$attr->comment;
		}
		$pragma = "";
		if ($attr->isPK()) {
			$pragma = "\n".$ident."#pragma db id";
			if ($attr->isInt()) {
				$pragma .= " auto";
			}
			if ($attr->name === "id") {
				$pragma .= ' column("_id")';
			}
			$pragma .= "\n";
		}
		else if ($attr->notnull) {
			$pragma .= "\n".$ident."#pragma db not_null\n";
		}
		$code .= $pragma.$def."\n";;
		return $code;
	}

	private function genRelMember($rel){
		$code = "";
		$def = QtTypes::sharedp(snakeToCamelUC($rel->tableRef))." ".camelToSnake($rel->name_).";";
		if ($rel->comment != "") {
			$def .= " // ".$rel->comment;
		}
		$pragma = "\n#pragma db ";
		if ($rel->notnull) {
			$pragma .= "not_null ";
		}
		$pragma .= "column(\"".$rel->name_fk."\")\n";
		$code .= $pragma.$def."\n";;
		return $code;
	}

	private function genConstructor($withAttrs){
		$code = $this->className."(";
		if ($withAttrs && count($this->attrs) > 0) {
			foreach ($this->attrs as $attr) {
				if (strpos($code, "const") > 0) {
					$code .= str_repeat(" ", strlen($this->className."("));
				}
				$code .= "const ".$attr->type."& ".$attr->name.",\n";
			}
			$code = substr($code, 0, strlen($code) - 2);//remove last ",\n"
			$code.= ")\n\t: ";
			foreach ($this->attrs as $attr) {
				$code .= "\t".$attr->name_." (".$attr->name."),\n";
			}
			$code = substr($code, 0, strlen($code) - 2);//remove last ",\n"
			$code.= "\n{\n}\n";

		} else {
			$code .= "){}";
		}
		return $code;
	}

	private function getter($attr){
		$code = "const ".$attr->type."&\n";
		$code.= $attr->name." () const\n";
		$code.= "{\n\treturn ".$attr->name_.";\n}\n";
		return $code;
	}
		private function getterRel($rel){
		//$code = "const ".$attr->type."&\n";
		//$code.= $attr->name." () const\n";
		//$code.= "{\n\treturn ".$attr->name_.";\n}\n";
		//return $code;
		return null;
	}

	/**
	* @param $info array of stdObject with attrs' mysql info
	* @return an array with Cpp attributes' info objects
	*/
	static function normalizeAttrs($info){
		$attrs = array();
		foreach ($info as $mysqlAttr) {
			$attrs[] = new CppAttr($mysqlAttr);
		}
		return $attrs;
	}

/**
	* @param $info array of stdObject with relations' mysql info
	* @return an array with Cpp attributes' info objects
	*/
	static function normalizeRels($info){
		$rels = array();
		foreach ($info as $mysqlRel) {
			$rels[] = new CppRelation($mysqlRel);
		}
		return $rels;
	}


	public function genIncludes($attrs, $rels){
		$includes = array();
		$code = "#include <QtCore/QSharedPointer>\n";
		foreach ($attrs as $attr) {
			if(startsWith($attr->type, 'Q')  && !in_array($attr->type, $includes)){
				$includes[] = $attr->type;
				$code .= '#include <QtCore/'.$attr->type.">\n";
			}
		}
		foreach ($rels as $rel) {
			if(!in_array($rel->type, $includes)){
				$includes[] = $rel->type;
				$code .= "#include \"".$rel->type.".hxx\"\n";
			}
		}
		$code .= "\n#include <odb/core.hxx>\n";
		return $code."\n";
	}

	public function generate($asHeader){

		$code = "";
		if ($asHeader) {
			$code .= "#ifndef ".strtoupper($this->className)."_HXX\n";
			$code .= "#define ".strtoupper($this->className)."_HXX\n\n";
		}

		$code .= $this->genIncludes($this->attrs, $this->rels);
		$public = "\tpublic: \n";
		if ($this->genConstructor(true) !== $this->genConstructor(false)) {
			$public.= self::autoIdent("\t\t", $this->genConstructor(true))."\n";
		}
		$private = "\tprivate:\n";
		$private.= "\t\tfriend class odb::access;\n";
		$private.= "\t\t".$this->genConstructor(false)."\n";

		foreach ($this->attrs as $attr) {
			$private.= $this->genAttr($attr,"\t\t");
			//$public .= $this->genGetter($attr,!$asHeader,"\t\t");
			//$public .= $this->genSetter($attr,!$asHeader,"\t\t");
			$public .= self::autoIdent("\t\t", $this->getter($attr))."\n";
			//$public .= "\n";
		}

		foreach ($this->rels as $rel) {
			$private.= self::autoIdent("\t\t", $this->genRelMember($rel,"\t\t"));
			//$public .= $this->genGetter($attr,!$asHeader,"\t\t");
			//$public .= $this->genSetter($attr,!$asHeader,"\t\t");
			$public .= self::autoIdent("\t\t", $this->getterRel($rel));
			//$public .= "\n";
		}
		$code .= "#pragma db object table(\"".$this->tableName."\")\n";
		$code .= "class ".$this->className."{\n\n";
		$code .= $private."\n";
		$code .= $public."\n";
		$code .= "};";

		if ($asHeader) {
			$code .= "\n#endif //".strtoupper($this->className)."_HXX";
		}
		return $code;
	}

	private function doesntHaveId(){
		foreach ($this->attrs as $attr) {
			if ($attr->key_type == "PRI") {
				return false;
			}
		}
		return true;
	}

	private function addDefaultId(){
		$defaultId = new CppAttr(null);
		$defaultId->name = "id";
		$defaultId->name_ = "id_";
		$defaultId->type = "int";
		$defaultId->notnull = true;
		$defaultId->key_type = "PRI";
		$defaultId->comment = "Added automatically, by default id...";
		$this->attrs[] = $defaultId;
	}

	static function autoIdent($tabs, $code){
		return $tabs.join("\n".$tabs, explode("\n", $code))."\n";
	}
}

/**
* Class to normalize the info recovered from infoschema
*/
class CppRelation
{
	
	public $name, $name_, $name_fk, $tableRef, $type, $notnull, $comment;

	function __construct($mysqlRel)
	{
		if ($mysqlRel === null) {
			return;
		}
		if (endsWith($mysqlRel->c, "_id")) {
			$this->name = substr($mysqlRel->c, 0, count($mysqlRel->c) - 3);
		}
		else $this->name = $mysqlRel->c;
		$this->name_fk = $mysqlRel->c;
		$this->name_ = $this->name;
		$this->tableRef = $mysqlRel->tref;
		$this->type = snakeToCamelUC($mysqlRel->tref);
		$this->notnull = $mysqlRel->cnull !== 'YES';
	}
}


/**
* Class to normalize the info recovered from infoschema
*/
class CppAttr
{
	public $name, $name_, $type, $notnull, $extra, $key_type, $comment;

	public function __construct($mysqlAttr)
	{
		if ($mysqlAttr === null) {
			return;
		}
		$this->name = $mysqlAttr->cname;
		$this->name_ = $mysqlAttr->cname."_";
		$this->type = $this->cppType($mysqlAttr->ctype);
		$this->notnull = $mysqlAttr->cnull !== 'YES';
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
			case 'binary': return 'QByteArray';
			case 'bit': return 'bool';
			case 'char': return 'QString';
			case 'date': return 'QDate';
			case 'datetime': return 'QDate';
			case 'datetime2': return 'QDate';
			case 'decimal': return 'double';
			case 'double': return 'double';
			case 'float': return 'float';
			case 'image': return 'QByteArray';
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
			case 'varbinary': return 'QByteArray';
			case 'varchar': return 'QString';
			case 'year': return 'int';
			default: return 'UNKNOWN_'.$mysqlType; 
		}
	}
}

