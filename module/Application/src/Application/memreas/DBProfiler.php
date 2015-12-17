<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use Zend\ServiceManager\ServiceManager;

class DBProfiler {
	public function logQueries($sl) {
		error_log ( "Inside DBProfiler..." );
		$profiler = $sl->get ( 'Zend\Db\Adapter\Adapter' )->getProfiler ();
		$queryProfiles = $profiler->getQueryProfiles ();
		
		foreach ( $queryProfiles as $key => $row ) {
			error_log ( print_r ( $row->toArray (), true ) );
		}
	}
}
?>
