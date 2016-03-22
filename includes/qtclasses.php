<?php

class Qt{
	
	static function QSharedPointer($class){
		return "QSharedPointer<".$class.">";
	}
	static function QList($class){
		return "QList<".$class.">";
	}

	static function QLazyWeakPointer($class){
		return "QLazyWeakPointer<".$class.">";
	}
}