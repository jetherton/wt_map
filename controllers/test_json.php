<?php defined('SYSPATH') or die('No direct script access.');
/**
 * For testing only
 */

class Test_json_Controller extends Template_Controller
{
    public $auto_render = false;

    // Main template
    public $template = null;

    // Table Prefix
    protected $table_prefix;

    public function __construct()
    {
        parent::__construct();
	
	
        // Set Table Prefix
        $this->table_prefix = Kohana::config('database.default.table_prefix');

	// Cacheable JSON Controller
	$this->is_cachable = TRUE;
    }


    /**
     * Test things
     */
    function index()
    {
		//got to let them know what we're dumping
		header('Content-type: application/json');
		//setup the output:
		echo '{"type": "FeatureCollection","features": [';
		//setup the query		
		$sql = 'SELECT *  FROM  `incident`  LEFT JOIN  `location` ON  `incident`.`location_id` = location.id';
		
		//get access to the DB
		//$db = new Database();
		//run the query
		//$results = $db->query($sql);
		
		
		$user_name = Kohana::config('database.default.connection.user');
		$password = Kohana::config('database.default.connection.pass');
		$database = Kohana::config('database.default.connection.database');
		$server = Kohana::config('database.default.connection.host');
		$db_handle = mysql_connect($server, $user_name, $password);
		$db_found = mysql_select_db($database, $db_handle);

		if ($db_found) 
		{


			$result = mysql_query($sql);

			while ($db_field = mysql_fetch_assoc($result)) {
				echo '{"type":"Feature","properties":{"id":"'.$db_field['id'];
				//print_r($db_field);
			}
			mysql_close($db_handle);
		}
		
		//close the output
		echo ']}';
    }
}