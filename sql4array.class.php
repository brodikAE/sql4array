<?php

/*
+-----------------------------------------------+
|                                               |
| > Absynthe sql4array class                    |
| > Written By Absynthe                         |
| > Adapted By brodikAE                         |
| > Date project: 04.05.2007                    |
| > Date started: 04.11.2008                    |
| > Date update: 10.03.2009                     |
|                                               |
+-----------------------------------------------+

original script: https://www.phpclasses.org/package/3879-PHP-Query-PHP-arrays-using-an-SQL-dialect.html

Serialize the array before parse!

Clauses available :
SELECT, DISTINCT, FROM, WHERE, ORDER BY, LIMIT, OFFSET

Operators available :
=, <, >, <=, >=, <>, !=, IS, IS IN, IS NOT, IS NOT IN, LIKE, ILIKE, NOT LIKE, NOT ILIKE, BETWEEN, NOT BETWEEN, REGEXP, RLIKE, NOT REGEXP

Functions available in WHERE clause :
LOWER(var), LCASE(var), UPPER(var), UCASE(var), TRIM(var)
*/
 
class sql4array
{

	/* Init
	-------------------------------------------- */
	private $query              = FALSE;				// the last query
	private $parse_query        = FALSE;				// array of the last query
	private	$replaced_table     = FALSE;				// values of the table
	private $parse_query_lower  = FALSE;				// lowercase array of the last query
	private $parse_select       = FALSE;				// array of the 'select' clause
	private $parse_select_as    = FALSE;				// array of the 'select' clause with alias of the column as key and column name as value 
	private $parse_from         = FALSE;				// value of the 'from' clause
	private $parse_from_as      = FALSE;				// array of the 'from' clause with alias of the table as key and table name as value
	private $array_columns      = FALSE;				// array with all columns of the table listed
	private $parse_where        = FALSE;				// string with the PHP condition
	private $distinct_query     = FALSE;				// boolean
	private $pass               = FALSE;
	private $table              = FALSE;
	private $time_start         = 0;
	private $time_end           = 0;
	private $response           = array();

	/* Query function
	-------------------------------------------- */
	public function query($query)
	{

		/* Initialization
		-------------------------------------------- */
		$this->destroy();
		$this->query = $query;
		$this->time_start	= microtime(true);
		
		/* Query parsing
		-------------------------------------------- */
		$this->parse_query();
		$this->parse_select();
		$this->parse_select_as();
		$this->parse_from();
		$this->parse_from_as();
		$this->get_array_columns();
		$this->parse_where();
		$this->parse_order();

		/* Query execution
		-------------------------------------------- */
		$this->exec_query();

		$this->time_end = microtime(true);
	
		return $this->response;

	}
	
	/* Query duration
	-------------------------------------------- */
	public function duration()
	{
		return $this->time_end - $this->time_start;
	}
	
	/* Destroy current values
	-------------------------------------------- */
	private function destroy()
	{
		$this->query              = FALSE;
		$this->parse_query        = FALSE;
		$this->replaced_table     = FALSE;
		$this->parse_query_lower  = FALSE;	
		$this->parse_select       = FALSE;
		$this->parse_select_as    = FALSE;
		$this->parse_from         = FALSE;
		$this->parse_from_as      = FALSE;
		$this->parse_where        = FALSE;
		$this->array_columns      = FALSE;
		$this->distinct_query     = FALSE;
		$this->time_start         = 0;
		$this->time_end           = 0;
		$this->table              = FALSE;
		$this->pass               = FALSE;
		$this->response           = array();
	}
	
	/* Parse SQL query
	-------------------------------------------- */
	private function parse_query()
	{
		$this->parse_query = preg_replace('#ORDER(\s){2,}BY(\s+)(.*)(\s+)(ASC|DESC)#i', 'ORDER BY \\3 \\5', $this->query);

		$e = 0;
		while(preg_match('#(s|a|i|[^\}]):(\d+)(?:[:](\{)(.*?);)(\}{2,})#', $this->parse_query, $match_table)){
			$this->replaced_table[$e] = $match_table[0];
			$this->parse_query = str_replace($match_table[0], "tabella_".$e, $this->parse_query);
			$e++;
		}

		$this->parse_query = preg_split('#(SELECT|DISTINCT|FROM|JOIN|WHERE|ORDER(\s+)BY|LIMIT|OFFSET)+#', $this->parse_query, -1, PREG_SPLIT_DELIM_CAPTURE);
		
		if (count($this->parse_query) < 2)
			trigger_error("Unable to parse query, make sure all keywords are in uppercase", E_USER_ERROR);
		
		$this->parse_query = array_map('trim', $this->parse_query);
		$this->parse_query_lower = array_map('strtolower', $this->parse_query);
	}
	
	/* Parse SQL 'select' clause
	-------------------------------------------- */
	private function parse_select()
	{
		$key = array_search("distinct", $this->parse_query_lower);
		
		if ($key === FALSE)
			$key = array_search("select", $this->parse_query_lower);
		else
			$this->distinct_query = TRUE;
		
		$string	= $this->parse_query[$key+1];
		$arrays	= preg_split('#((\s)*,(\s)*)#i', $string, -1, PREG_SPLIT_NO_EMPTY);
		
		foreach ($arrays as $array)
			$this->parse_select[] = $array;

	}

	/* Parse again SQL 'select' clause with 'as' keyword
	-------------------------------------------- */
	private function parse_select_as()
	{
		foreach ($this->parse_select as $select)
		{
			if (eregi('as', $select))
			{
				$arrays	= preg_split('#((\s)+AS(\s)+)#i', $select, -1, PREG_SPLIT_NO_EMPTY);
				$this->parse_select_as[$arrays[1]] = $arrays[0];
			}
			else
			{
				$this->parse_select_as[$select] = $select;
			}
		}

	}
	
	/* Parse SQL 'from' clause
	-------------------------------------------- */
	private function parse_from()
	{
		$key				= array_search("from", $this->parse_query_lower);
		$this->parse_from	= $this->parse_query[$key+1];

		if(preg_match('#^(a:0:{}(;)?|s:0:""(;)?|N;)#', $this->parse_from))
			$this->pass = TRUE;

	}
	
	/* Parse again SQL 'from' clause with 'as' keyword
	-------------------------------------------- */
	private function parse_from_as()
	{

		if($this->pass)
			return;

		if (eregi('AS', $this->parse_from))
		{
			$arrays	= preg_split('#((\s)+AS(\s)+)#i', $this->parse_from, -1, PREG_SPLIT_NO_EMPTY);
			$table	= $arrays[0];	
			$this->parse_from_as[$arrays[1]]	= $table;
		}
		else
		{
			$table = $this->parse_from;
			$this->parse_from_as[$this->parse_from]	= $table;
		}

		$table = preg_replace('#tabella_(\d+)#e', "\$this->replaced_table['\\1']", $table);

		$this->table = unserialize($table);

	}
	
	/* Parse again SQL 'from' clause with 'as' keyword
	-------------------------------------------- */
	private function get_array_columns()
	{
		if($this->pass)
			return;

		if (count($this->table) > 0)
		{

			foreach (current($this->table) as $key => $value)
				$this->array_columns[$key] = $key;
				
			foreach ($this->array_columns as $key => $value)
				if (array_search($key, $this->parse_select_as) !== FALSE)
					$this->array_columns[array_search($key, $this->parse_select_as)] = $value;
			
		}
		else
			trigger_error("Array given as table is empty.", E_USER_ERROR);
	}
	
	/* Parse SQL 'where' clause
	-------------------------------------------- */
	private function parse_where()
	{

		if($this->pass)
			return;

		$key = array_search("where", $this->parse_query_lower);

		if ($key === FALSE)
			return $this->parse_where = "return TRUE;";
		
		$string	= $this->parse_query[$key+1];
		
		if (trim($string) == '')
			return $this->parse_where =  "return TRUE;";

		/* SQL Functions
		-------------------------------------------- */
		$patterns[]		= '#(LOWER|LCASE)\((.*)\)#ie';
		$patterns[]		= '#(UPPER|UCASE)\((.*)\)#ie';
		$patterns[]		= '#TRIM\((.*)\)#ie';
	
		$replacements[]	= "'strtolower(\\2)'";
		$replacements[]	= "'strtoupper(\\2)'";
		$replacements[]	= "'trim(\\1)'";

		/* Basics SQL operators
		-------------------------------------------- */
		$patterns[]		= '#(([a-zA-Z0-9\._]+)(\())?([a-zA-Z0-9\._]+)(\))?(\s)+(=|IS)(\s)+([[:digit:]]+)(\s)?#ie';
		$patterns[]		= '#(([a-zA-Z0-9\._]+)(\())?([a-zA-Z0-9\._]+)(\))?(\s)+(=|IS)(\s)+(\'|\")(.*?)(\'|\")(\s)?#ie';
		$patterns[]		= '#(([a-zA-Z0-9\._]+)(\())?([a-zA-Z0-9\._]+)(\))?(\s)+(>|<|>=|<=)(\s)+([[:digit:]]+)(\s)?#ie';
		$patterns[]		= '#(([a-zA-Z0-9\._]+)(\())?([a-zA-Z0-9\._]+)(\))?(\s)+(>|<|>=|<=)(\s)+(\'|\")(.*?)(\'|\")(\s)?#ie';
		$patterns[]		= '#(([a-zA-Z0-9\._]+)(\())?([a-zA-Z0-9\._]+)(\))?(\s)+(<>|IS NOT|!=)(\s)+([[:digit:]]+)(\s)?#ie';
		$patterns[]		= '#(([a-zA-Z0-9\._]+)(\())?([a-zA-Z0-9\._]+)(\))?(\s)+(<>|IS NOT|!=)(\s)+(\'|\")(.*?)(\'|\")(\s)?#ie';
		$patterns[] 	= '#(([a-zA-Z0-9\._]+)(\())?([a-zA-Z0-9\._]+)(\))?(\s)+(IS\s*)?(IN)(\s)+\((.*?)\)(\s)?#ie';
		$patterns[] 	= '#(([a-zA-Z0-9\._]+)(\())?([a-zA-Z0-9\._]+)(\))?(\s)+(IS\s*)?(NOT IN)(\s)+\((.*?)\)(\s)?#ie';
		$patterns[] 	= '#(([a-zA-Z0-9\._]+)(\())?([a-zA-Z0-9\._]+)(\))?(\s)+(IS\s*)?(BETWEEN)(\s)+([[:digit:]]+)(\s)+(AND)(\s)+([[:digit:]]+)(\s)?#ie';
		$patterns[] 	= '#(([a-zA-Z0-9\._]+)(\())?([a-zA-Z0-9\._]+)(\))?(\s)+(IS\s*)?(BETWEEN)(\s)+(\'|\")(.*?)(\'|\")(\s)+(AND)(\s)+(\'|\")(.*?)(\'|\")(\s)?#ie';
		$patterns[] 	= '#(([a-zA-Z0-9\._]+)(\())?([a-zA-Z0-9\._]+)(\))?(\s)+(IS\s*)?(NOT BETWEEN)(\s)+([[:digit:]]+)(\s)+(AND)(\s)+([[:digit:]]+)(\s)?#ie';
		$patterns[] 	= '#(([a-zA-Z0-9\._]+)(\())?([a-zA-Z0-9\._]+)(\))?(\s)+(IS\s*)?(NOT BETWEEN)(\s)+(\'|\")(.*?)(\'|\")(\s)+(AND)(\s)+(\'|\")(.*?)(\'|\")(\s)?#ie';
		$patterns[] 	= '#(([a-zA-Z0-9\._]+)(\())?([a-zA-Z0-9\._]+)(\))?(\s)+(REGEXP|RLIKE)(\s)+(\'|\")(.*?)(\'|\")(\s)?#ie';
		$patterns[] 	= '#(([a-zA-Z0-9\._]+)(\())?([a-zA-Z0-9\._]+)(\))?(\s)+(NOT REGEXP)(\s)+(\'|\")(.*?)(\'|\")(\s)?#ie';
		
		$replacements[]	= "'\\1'.\$this->parse_where_key(\"\\4\").'\\5 == \\9 '";
		$replacements[]	= "'\\1'.\$this->parse_where_key(\"\\4\").'\\5 == \"\\10\" '";
		$replacements[]	= "'\\1'.\$this->parse_where_key(\"\\4\").'\\5 \\7 \\9 '";
		$replacements[]	= "'\\1'.\$this->parse_where_key(\"\\4\").'\\5 \\7 \\10 '";
		$replacements[]	= "'\\1'.\$this->parse_where_key(\"\\4\").'\\5 != \\9 '";
		$replacements[]	= "'\\1'.\$this->parse_where_key(\"\\4\").'\\5 != \"\\10\" '";
		$replacements[]	= "'in_array(\\1'.\$this->parse_where_key(\"\\4\").'\\5, array('.\$this->parse_in(\"\\10\").')) '";
		$replacements[]	= "'!in_array(\\1'.\$this->parse_where_key(\"\\4\").'\\5, array('.\$this->parse_in(\"\\10\").')) '";
		$replacements[]	= "'\\1'.\$this->parse_where_key(\"\\4\").'\\5 >= \"\\10\" && \\1'.\$this->parse_where_key(\"\\4\").'\\5 <= \"\\14\" '";
		$replacements[]	= "'\\1'.\$this->parse_where_key(\"\\4\").'\\5 >= \"\\11\" && \\1'.\$this->parse_where_key(\"\\4\").'\\5 <= \"\\17\" '";
		$replacements[]	= "'\\1'.\$this->parse_where_key(\"\\4\").'\\5 < \"\\10\" || \\1'.\$this->parse_where_key(\"\\4\").'\\5 > \"\\14\" '";
		$replacements[]	= "'\\1'.\$this->parse_where_key(\"\\4\").'\\5 < \"\\11\" || \\1'.\$this->parse_where_key(\"\\4\").'\\5 > \"\\17\" '";
		$replacements[]	= "'preg_match(\"#\\10#\", \"\\1'.\$this->parse_where_key(\"\\4\").'\\5\")'";
		$replacements[]	= "'!preg_match(\"#\\10#\", \"\\1'.\$this->parse_where_key(\"\\4\").'\\5\")'";

		/* match SQL operators
		-------------------------------------------- */
		$ereg = array('%' => '(.*)', '_' => '(.)');
		
		$patterns[] 	= '#([a-zA-Z0-9\._]+)(\s)+LIKE(\s)*(\'|\")(.*?)(\'|\")#ie';
		$patterns[] 	= '#([a-zA-Z0-9\._]+)(\s)+ILIKE(\s)*(\'|\")(.*?)(\'|\")#ie';
		$patterns[] 	= '#([a-zA-Z0-9\._]+)(\s)+NOT LIKE(\s)*(\'|\")(.*?)(\'|\")#ie';
		$patterns[] 	= '#([a-zA-Z0-9\._]+)(\s)+NOT ILIKE(\s)*(\'|\")(.*?)(\'|\")#ie';
		
		$replacements[]	= "'ereg(\"'.strtr(\"\\5\", \$ereg).'\", '.\$this->parse_where_key(\"\\1\").')'";
		$replacements[]	= "'eregi(\"'.strtr(\"\\5\", \$ereg).'\", '.\$this->parse_where_key(\"\\1\").')'";
		$replacements[]	= "'!ereg(\"'.strtr(\"\\5\", \$ereg).'\", '.\$this->parse_where_key(\"\\1\").')'";
		$replacements[]	= "'!eregi(\"'.strtr(\"\\5\", \$ereg).'\", '.\$this->parse_where_key(\"\\1\").')'";
		
		$this->parse_where = "return ".stripslashes(trim(preg_replace($patterns, $replacements, $string))).";";
	}
	
	/* Return variable to test
	-------------------------------------------- */
	private function parse_where_key($key)
	{

		if($this->pass)
			return;

		if (ereg('\.', $key))
		{
			list($table, $col) = explode('.', $key);
			return '$row['.$this->array_columns[$col].']';
		}
		else
		{
			return '$row['.$this->array_columns[$key].']';
		}
	}
	 
	/* Format IN clause for PHP
	-------------------------------------------- */
	private function parse_in($string)
	{
		$array	= explode(',', $string);
		$array	= array_map('trim', $array);

		return implode(', ', $array);
	}
	
	/* Parse SQL order by parameters
	-------------------------------------------- */
	private function parse_order()
	{

		if($this->pass)
			return;

		$key	= array_search("order by", $this->parse_query_lower);
		
		if ($key === FALSE)
			return;
		
		$string	= $this->parse_query[$key+2];
		$arrays	= explode(',', $string);
		
		if (!is_array($arrays))
			$arrays[] = $string;
		
		$arrays	= array_map('trim', $arrays);
		
		$multisort	= "array_multisort(";
		
		foreach ($arrays as $array)
		{
			list($col, $sort)	= preg_split('#((\s)+)#', $array, -1, PREG_SPLIT_NO_EMPTY);
			$multisort			.= "\$this->split_array(\$this->table, '$col'), SORT_".strtoupper($sort).", SORT_REGULAR, ";
		}
		
		$multisort	.= "\$this->table);";

		eval($multisort);
	}

	/* Execute query
	-------------------------------------------- */
	private function exec_query()
	{

		if($this->pass)
			return;

		$koffset	= array_search("offset", $this->parse_query_lower);
		$klimit		= array_search("limit", $this->parse_query_lower);
		
		if ($koffset !== FALSE)
			$offset	= (int) $this->parse_query[$koffset+1];

		if ($klimit !== FALSE){
		$limit	= (int) $this->parse_query[$klimit+1];
			if(preg_match('#(\d+)(\s*),(\s*)(\d*)#', $this->parse_query[$klimit+1], $off)){
				$offset	= (int) $off[1];
				$limit	= (int) $off[4];
				$koffset = TRUE;
			}
		}
	
		$irow		= 0;
		$distinct	= array();

		foreach ($this->table as $row)
		{
			// Offset
			if ($koffset !== FALSE && $irow < $offset)
			{
				$irow++;
				continue;
			}

			if (eval($this->parse_where))
			{
				if ($this->parse_select_as[0] == '*')
				{		
					if ($this->distinct_query && in_array($row, $distinct))
						continue;
					else if (!$this->distinct_query)
						$this->response[] = $row;
					else
					{
						$this->response[]	= $row;
						$distinct[]			= $row;
					}

				}		
				else
				{
					foreach ($this->parse_select_as as $key => $value)
						$temp[$key] = $row[$value];
					
					if ($this->distinct_query && in_array($row, $distinct))
						continue;
					else if (!$this->distinct_query)
						$this->response[] = $row;
					else
					{
						$this->response[]	= $row;
						$distinct[]			= $row;
					}
				}
				
				// Limit
				if ($klimit !== FALSE && count($this->response) == $limit)
					break;
			}
			
			$irow++;
		}

	}
	
	/* Return response
	-------------------------------------------- */
	private function return_response()
	{
		return $this->response;
	}
	
	/* Return a column of an array
	-------------------------------------------- */
	private function split_array($input_array, $column)
	{	
		$output_array	= array();
		
		foreach ($input_array as $key => $value)
			$output_array[] = $value[$column];
		
		return $output_array;
	}
	
	/* Entire array search
	-------------------------------------------- */
	private function entire_array_search($needle, $array)
	{
		foreach($array as $key => $value)
			if ($value === $needle)
				$return[] = $key;
				
		if (!is_array($return))
			$return = FALSE;
				
		return $return;
	}
}

?>
