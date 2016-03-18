<?php

class QtTypes {
	
	static function sharedp($class){
		return "QSharedPointer<".$class.">";
	}
}