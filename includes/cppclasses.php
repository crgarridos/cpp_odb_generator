<?php

include_once 'includes/utils.php';

include_once 'includes/qtclasses.php';
/**
* Generate C++ qt/obd compatible class from MySql table info
*/
class CppQtOdbClass{

	private $className, $tableName, $attrs, $relsOne, $relsMany;
	
	/**
	* @param $table the table's name
	* @param $attrs array of stdObject with attrs' info
	*/
	public function __construct($table, $attrs, $relsOne, $relsMany){
		$this->className = snakeToCamelUC($table);
		$this->tableName = strtolower($table);
		$this->attrs = self::normalizeAttrs($attrs);
		$this->relsOne = self::normalizeRels($relsOne, false);
		$this->relsMany = self::normalizeRels($relsMany, true);
		if ($this->doesntHaveId()) {
			$this->addDefaultId();
		}
	}

	private function genAttr($attr){
		$code = "";
		$def = $attr->type." ".$attr->name_.";";

		if(!$attr->notnull && in_array($attr->type, CppAttr::$NotNullTypes))
			$def = "odb::nullable<".$attr->type."> ".$attr->name_.";";

		if ($attr->comment != "") {
			$def .= " // ".$attr->comment;
		}
		$pragma = "";
		if ($attr->isPK()) {
			$pragma = "\n#pragma db id";
			if ($attr->isInt()) {
				$pragma .= " auto";
			}
			if ($attr->name === "id") {
				$pragma .= ' column("_id")';
			}
			$pragma .= "\n";
		}
		else if ($attr->notnull && !in_array($attr->type, CppAttr::$NotNullTypes)) {
			$pragma .= "\n#pragma db not_null\n";
		}
		$code .= $pragma.$def;
		return $code;
	}

	private function genRelMemberOne($rel){
		$code = "";
		$def = Qt::QSharedPointer(snakeToCamelUC($rel->tableRef))." ".$rel->name_.";";
		if ($rel->comment != "") {
			$def .= " // ".$rel->comment;
		}
		$pragma = "\n#pragma db ";
		if ($rel->notnull) {
			$pragma .= "not_null ";
		}
		$pragma .= "column(\"".$rel->name_fk."\")\n";
		$code .= $pragma.$def;
		return $code;
	}

	private function genRelMemberMany($rel){
		$code = "";
		$def = Qt::QList(Qt::QLazyWeakPointer(snakeToCamelUC($rel->tableRef)))." ".$rel->name_.";";
		if ($rel->comment != "") {
			$def .= " // ".$rel->comment;
		}
		$pragma = "\n#pragma db ";
		if ($rel->notnull) {
			$pragma .= "value_not_null ";
		}
		$pragma .= "inverse(".$rel->name_fk.")\n";
		$code .= $pragma.$def;
		return $code;
	}

	private function genDestructor(){
		return "~".$this->className."();";
	}

	private function genConstructor($withAttrs){
		$code = $this->className."(";
		$args = "";
		$body = "";
		if ($withAttrs && count($this->attrs)+count($this->relsOne) > 0) {
			foreach ($this->attrs as $attr) {
				if ($attr->isPK() && $attr->isInt()) {
					continue;
				}
				if (strpos($args, "const") > 0) {
					$args .= str_repeat(" ", strlen($code));
				}
				$args .= "const ".$attr->type."& ".$attr->name.",\n";
				$body .= "\t".$attr->name_." (".$attr->name."),\n";
			}
			$invArgs = array();
			$invBody = array();
			// iterating on inverse array to validate the last value as default
			$stopDefault = false;
			for ($i = count($this->relsOne) -1 ; $i >= 0; $i--) {
				$rel = $this->relsOne[$i];
				$default = "";
				if(!$rel->notnull && !$stopDefault)
					$default = " = ".Qt::QSharedPointer($rel->type)."()";
				$invArgs[] = "const ".Qt::QSharedPointer($rel->type)." ".$rel->name.$default.",\n";
				$invBody[] = "\t".$rel->name_." (".$rel->name."),\n";
			}
			for ($i = count($invArgs) -1 ; $i >= 0; $i--) {
				if (strpos($args, "const") > 0) {
					$args .= str_repeat(" ", strlen($code));
				}
				$args .= $invArgs[$i];
				$body .= $invBody[$i];
			}
			$code.= endsWith($args, ",\n") ? substr($args, 0, strlen($args) - 2) : $args;//remove last ",\n"
			$code.= ")\n\t: ";
			$code.= endsWith($body, ",\n") ? substr($body, 0, strlen($body) - 2) : $body;//remove last ",\n"
			$code.= "\n{\n}\n";

		} else {
			$code .= "){}";
		}
		return $code;
	}

	private function getter($attr){
		$code = "const ".$attr->type."&\n";
		if($attr->nullable())
			$code = "const odb::nullable<".$attr->type.">\n";
		$code.= "get".snakeToCamelUC($attr->name)." () const\n";
		$code.= "{\n\treturn ".$attr->name_.";\n}";
		return $code;
	}

	private function setter($attr){
		$code = "void set".snakeToCamelUC($attr->name)." (const ".$attr->type." ".$attr->name.")\n";
		$code.= "{\n\t".$attr->name_." = ".$attr->name.";\n}";
		return $code;
	}
	
	private function getterRelOne($rel){
		$code = Qt::QSharedPointer($rel->type)."\n";
		$code.= "get".snakeToCamelUC($rel->name)." () const\n";
		$code.= "{\n\treturn ".$rel->name_.";\n}";
		return $code;
	}


	private function setterRelOne($attr){
		$code = "void set".snakeToCamelUC($attr->name)." (".Qt::QSharedPointer($attr->type)." ".$attr->name.")\n";
		$code.= "{\n\t".$attr->name_." = ".$attr->name.";\n}";
		return $code;
	}

	private function getterRelMany($rel){
		$code = Qt::QList(Qt::QLazyWeakPointer($rel->type))."\n";
		$code.= "get".snakeToCamelUC($rel->name)." () const\n";
		$code.= "{\n\treturn ".$rel->name_.";\n}";
		return $code;
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
	static function normalizeRels($info, $toMany){
		$rels = array();
		foreach ($info as $mysqlRel) {
			$rels[] = $toMany ? new CppRelationMany ($mysqlRel) : new CppRelationOne($mysqlRel);
		}
		return $rels;
	}


	public function genIncludes($attrs, $relsOne, $relsMany){
		$includes = array();
		$odb = "";
		$qt = "#include <QtCore/QObject>\n";
		$forw = "";
		$foot = "";
		foreach ($attrs as $attr) {
			// $code .= json_encode($attr)."\n";
			if(startsWith($attr->type, 'Q') && !in_array($attr->type, $includes)){
				$includes[] = $attr->type;
				$qt .= '#include <QtCore/'.$attr->type.">\n";
			}
			else if (!$attr->notnull && in_array($attr->type, CppAttr::$NotNullTypes)
				&& !in_array("nullable", $includes)) {
				$includes[] = "nullable";
				$odb .= "#include <odb/nullable.hxx>\n";
			}
		}
		
		if(count($relsOne) > 0)
			$qt .= "#include <QtCore/QSharedPointer>\n";
		if(count($relsMany) > 0){
			$qt.= "#include <QtCore/QList>\n";
			$odb.= "#include <odb/qt/lazy-ptr.hxx>\n";
		}

		foreach ($relsOne as $rel) {
			if(!in_array($rel->type, $includes)){
				$includes[] = $rel->type;
				$forw .= "class ".$rel->type.";\n";
				$foot .= "#include \"".$rel->type.".hxx\"\n";
			}
		}
		foreach ($relsMany as $rel) {
			if(!in_array($rel->type, $includes)){
				$includes[] = $rel->type;
				$forw .= "class ".$rel->type.";\n";
				$foot .= "#include \"".$rel->type.".hxx\"\n";
			}
		}
		$odb .= "#include <odb/core.hxx>\n";
		return (object)[
			"code" => $qt."\n".$odb."\n".$forw."\n",
			"foot" => $foot
		];
	}

	public function typedef(){
		$code = "class ".$this->className.";\n";
		$code .= "typedef ".Qt::QSharedPointer($this->className)." ".$this->className."Shared;\n";
		$code .= "typedef ".Qt::QWeakPointer($this->className)." ".$this->className."Weak;\n"; 
		return $code;
	}

	public function generate($asHeader){

		$code = "";
		if ($asHeader) {
			$code .= "#ifndef ".strtoupper($this->className)."_HXX\n";
			$code .= "#define ".strtoupper($this->className)."_HXX\n\n";
		}

		$include = $this->genIncludes($this->attrs, $this->relsOne, $this->relsMany);
		$code .= $include->code;
		$code .= $this->typedef()."\n";
		$public = "public: \n";
		if ($this->genConstructor(true) !== $this->genConstructor(false)) {
			$public.= self::autoIdent("\t", $this->genConstructor(true))."\n";
		}
		$private = "private:\n";
		$private.= "\tfriend class odb::access;\n";
		$private.= "\t".$this->genConstructor(false);

		foreach ($this->attrs as $attr) {
			$private.= self::autoIdent("\t", $this->genAttr($attr));
			//$public .= $this->genGetter($attr,!$asHeader,"\t\t");
			//$public .= $this->genSetter($attr,!$asHeader,"\t\t");
			$public .= self::autoIdent("\t", $this->getter($attr))."\n";
			$public .= self::autoIdent("\t", $this->setter($attr))."\n";
			//$public .= "\n";
		}

		$private.= "\n\t// To One relations\n";
		foreach ($this->relsOne as $rel) {
			$private.= self::autoIdent("\t", $this->genRelMemberOne($rel,"\t\t"));
			//$public .= $this->genGetter($attr,!$asHeader,"\t\t");
			//$public .= $this->genSetter($attr,!$asHeader,"\t\t");
			$public .= self::autoIdent("\t", $this->getterRelOne($rel))."\n";
			$public .= self::autoIdent("\t", $this->setterRelOne($rel))."\n";
			//$public .= "\n";
		}

		$private.= "\n\t// To Many relations\n";
		foreach ($this->relsMany as $rel) {
			if (CppRelationMany::isUnique($rel, $this->relsMany)) {
				$private.= self::autoIdent("\t", $this->genRelMemberMany($rel,"\t\t"));
				//$public .= $this->genGetter($attr,!$asHeader,"\t\t");
				//$public .= $this->genSetter($attr,!$asHeader,"\t\t");
				$public .= self::autoIdent("\t", $this->getterRelMany($rel))."\n";
				//$public .= "\n";	
			}
		}
		$code .= "#pragma db object table(\"".$this->tableName."\")\n";
		$code .= "class ".$this->className."\n";// : public QObject \n";
		$code .= "{\n";
		//$code .= "\tQ_OBJECT\n\n";
		$code .= $public;
		$code .= $private;
		$code .= "};\n\n";

		if ($asHeader) {
			if($include->foot !== ""){
				$code .= "#ifdef ODB_COMPILER\n";
				$code .= self::autoIdent("\t",$include->foot);
				$code .= "#endif //ODB_COMPILER\n";
			}
			$code .= "#endif //".strtoupper($this->className)."_HXX";
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
class CppRelationMany
{
	public $name, $name_, $name_fk, $tableRef, $type, $notnull, $comment;

	function __construct($mysqlRel)
	{
		if ($mysqlRel === null) {
			return;
		} 
		$this->type = snakeToCamelUC($mysqlRel->t);
		$this->tableRef = $mysqlRel->t;

		$this->name = camelToSnake($mysqlRel->c);
		if (endsWith($this->name, "_id")) {
			$this->name = substr($this->name, 0, strlen($this->name) - 3);
		}

		$this->name_fk = $this->name."_";
		$this->name = pluralFirstSnake($this->tableRef);//."_".$this->name;

		$this->name_ = $this->name."_";
		$this->notnull = $mysqlRel->cnull !== 'YES';
	}

	public static function isUnique($toFind, $arrRels){
		$counter = 0;
		foreach ($arrRels as $rel) {
			if ($rel->name === $toFind->name) {
				$counter++;
				if ($counter > 1) {
					return false;
				}
			}
		}
		return $counter == 1;
	}
}

/**
* Class to normalize the info recovered from infoschema
*/
class CppRelationOne
{
	
	public $name, $name_, $name_fk, $tableRef, $type, $notnull, $comment;

	function __construct($mysqlRel)
	{
		if ($mysqlRel === null) {
			return;
		} 
		$this->name = camelToSnake($mysqlRel->c);
		if (endsWith($this->name, "_id")) {
			$this->name = substr($this->name, 0, strlen($this->name) - 3);
		}
		$this->name_fk = camelToSnake($mysqlRel->c);
		$this->name_ = $this->name."_";
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

	public static $NotNullTypes = array('int','long','bool','float','double');

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

	/**
	* The attribute should be declared as nullable ?
	* @return the answer to the question aboiv.
	*/
	public function nullable(){
		return !$this->notnull && in_array($this->type, CppAttr::$NotNullTypes);
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
			case 'date': return 'QDateTime';
			case 'datetime': return 'QDateTime';
			case 'datetime2': return 'QDateTime';
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
			case 'smalldatetime': return 'QDateTime';
			case 'smallint': return 'int';
			case 'mediumint': return 'int';
			case 'smallmoney': return 'double';
			case 'text': return 'QString';
			case 'time': return 'QDateTime';
			case 'timestamp': return 'QDateTime';
			case 'tinyint': return 'bool';
			case 'uniqueidentifier': return 'QString';
			case 'varbinary': return 'QByteArray';
			case 'varchar': return 'QString';
			case 'year': return 'int';
			default: return 'UNKNOWN_'.$mysqlType; 
		}
	}
}

