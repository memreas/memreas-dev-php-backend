<?php
namespace Application\memreas;
use Application\memreas\Mlog;

class CheckGitPull
{

    protected $gitlock = "/var/www/ephemeral0/gitpull.lock";
    protected $github_basedir = "/var/www/memreas-dev-php-backend/";
    
    function execOps ($op)
    {
        $outarr = array();
        $ret = '';
        /**
         * Exec op and error log results...
         */
        exec($op, $outarr, $ret);
        Mlog::add(__CLASS__ . __METHOD__, '...');
        Mlog::add('$op', $op);
        Mlog::add('$outarr', $outarr);
        Mlog::add('$ret', $ret);
    }

    public function exec ($pull=false)
    {
        if (!file_exists($github_flag) || $pull) {
            ob_start();
            // Setup SSH agent
            execOps ( 'eval "$(ssh-agent -s)"' );
            
            error_log ( 'about to run ssh-add /home/srv/.ssh/id_rsa' );
            // Add key
            execOps ( "ssh-add ~/.ssh/id_rsa" );
            
            // check ssh auth sock
            execOps ( 'echo "$SSH_AUTH_SOCK"' );
            
            // check github access
            execOps ( 'ssh -T git@github.com' );
            
            // cd to $github_basedir
            execOps ( "cd $github_basedir" );
            
            // git pull
            execOps ( "git pull" );
            
            $output = ob_get_contents();
            
            //write lock file
            $file = fopen($gitlock,"w");
            echo fwrite($file,$output);
            fclose($file);
        }
    }
} // end class MemreasTranscoder