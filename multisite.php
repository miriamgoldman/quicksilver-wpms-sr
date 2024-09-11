<?php
use Symfony\Component\Process\Process;

require_once __DIR__ . '/vendor/autoload.php';

$env  = getenv( 'PANTHEON_ENVIRONMENT' );
$site = getenv( 'PANTHEON_SITE_NAME' );

if ( 'live' != $_POST[ 'to_environment' ] ) :


    echo 'Replacing domain names in wp_blogs table' . PHP_EOL;

    $domains = [
        'live'    => 'www.example.com',
        'default' => $env . '-' . $site . '.pantheonsite.io',
    ];


    // Figure out what the domain is for the current site.
    $domain_new = $domains[ $env ] ?: $domains['default'];


    // Get the primary blog's domain.
    $process     = run_wp_cli( [ 'db', 'query', 'SELECT domain FROM wp_blogs WHERE site_id=1 AND blog_id=1;', '--skip-column-names', "--url={$domains['live']}" ] );
    $domain_orig = trim( $process->getOutput() );


    // Get the list of sites.
    $process = run_wp_cli( [ 'db', 'query', 'SELECT blog_id, domain, path FROM wp_blogs WHERE site_id=1', '--skip-column-names', "--url={$domains['live']}" ] );
    $blogs   = explode( PHP_EOL, $process->getOutput() );

    // Update wp_site domain to the new domain.
    run_wp_cli( [ 'db', 'query', "UPDATE wp_site SET domain='{$domain_new}', path='/' WHERE id=1", "--url={$domains['live']}" ], true );

   $processes = [];

    // Update individual site urls.
    foreach ( $blogs as $blog_raw ) {
        $blog    = explode( "\t", $blog_raw );
        $blog_id = intval( $blog[0] );
      
        // If the blog ID isn't a positive integer, something's not right. Skip it.
        if ( 0 >= $blog_id ) {
            continue;
        }

        $blog_domain_orig = $blog[1];
        $blog_path_orig   = $blog[2];

        echo "Processing site #$blog_id {$blog_domain_orig}{$blog_path_orig}\n";


            // Process URLs to a subdirectory format.
            $blog_domain_new = $domain_new;

            if ( 1 == $blog_id ) {
                // First blog gets a path of just /
                $blog_path_new = '/';
            } else {
                // All other blogs get a path made of the subdomain and original path.
                $blog_path_new = str_replace( '.' . $domain_orig, '', $blog_domain_orig ) . $blog_path_orig;

                // Convert to a single subdirectory.
                $blog_path_new = '/' . str_replace( ['.', '/' ], '-', $blog_path_new );

                $blog_path_new = rtrim( $blog_path_new, '-' ) . '/';
            }
        
        $blog_url_new  = trim( "{$blog_domain_new}{$blog_path_new}", '/' );

        // Update wp_blogs record.
        run_wp_cli( [ 'db', 'query', "UPDATE wp_blogs SET domain='{$blog_domain_new}', path='{$blog_path_new}' WHERE site_id=1 AND blog_id={$blog_id}", "--url={$domains['live']}" ], false );
        if ( 1 != $blog_id ) :
        run_wp_cli( [ 'db', 'query', "UPDATE wp_{$blog_id}_options SET option_value='https://{$blog_url_new}' WHERE option_name='siteurl' OR option_name='home'", "--url={$domains['live']}" ], false );
        else :
            run_wp_cli( [ 'db', 'query', "UPDATE wp_options SET option_value='https://{$blog_url_new}' WHERE option_name='siteurl' OR option_name='home'", "--url={$domains['live']}" ], false );
        endif;

        
        
        // Search-replace limited to just the blog's tables for speed. Commented out due to sheer number of sites.
        /*
        $blog_url_orig = trim( "{$blog_domain_orig}{$blog_path_orig}", '/' );
        $blog_url_new  = trim( "{$blog_domain_new}{$blog_path_new}", '/' );
        
        $processes[] = run_wp_cli( [ 'search-replace', "//$blog_url_orig", "//$blog_url_new", "wp_options", "--url=$blog_url_new" ], true );
        

        echo "New site URL is now " . $blog_url_new;
        */

        while ( count ( $processes ) > 500 ) {
            $processes = clean_processes( $processes );
            sleep( 1 );
        }
    }

    // Wait for all processes to finish.
    while ( ! empty( $processes ) ) {
        $processes = clean_processes( $processes );

        sleep( 1 );

        printf( '%d processes executing', count( $processes ) );
        echo PHP_EOL;
    }
   
endif;

/**
 * Run a command through Symfony's process
 *
 * @param string $cmd   The command to run.
 * @param bool   $async Whether to run the command sync or async.
 * @return Symfony\Component\Process\Process;
 */
function run_wp_cli( $cmd, $async = false ) {
	$cmd = array_merge( [ 'wp' ], (array) $cmd, [ '--skip-plugins', '--skip-themes', '--quiet' ] );
	$process = new Process( $cmd );
	$process->setTimeout( 60 * 10 );

	if ( $async ) {
		$process->start();
	} else {
		$process->mustRun();
	}

	return $process;
}

/**
 * Clean out done processes
 *
 * @param array $processes Array of Symfony processes.
 * @return array           Original processes, with complete ones filtered out.
 */
function clean_processes( $processes ) {
	foreach ( $processes as $key => $process ) {
		if ( $process->isTerminated() ) {
			if ( ! $process->isSuccessful() ) {
				echo $process->getErrorOutput();
			}

			unset( $processes[ $key ] );
		}
	}

	return $processes;
}

