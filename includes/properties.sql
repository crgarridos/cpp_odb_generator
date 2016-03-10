-- if you change the name's keys, that will impact in all AttrClass Types
SELECT 
	COLUMN_NAME cname,
	DATA_TYPE AS ctype,
	-- JTYPE(DATA_TYPE) AS ctype,
	IS_NULLABLE cnull, 
    EXTRA cextra, 
    COLUMN_KEY ckey
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE table_name = "&table" AND TABLE_SCHEMA = "&db"
		AND COLUMN_NAME NOT IN
		(SELECT K.COLUMN_NAME c -- avoid fk attributes
		   FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE K
		  WHERE K.TABLE_NAME = "&table" AND K.REFERENCED_TABLE_NAME IS NOT NULL);