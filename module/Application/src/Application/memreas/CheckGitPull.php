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
        //exec($op, $outarr, $ret);
        $output = shell_exec($op . ' 2>&1');
        //Mlog::addone(__CLASS__ . __METHOD__, '...');
        //Mlog::addone('$op', $op);
        //Mlog::addone('$outarr', $outarr);
        //Mlog::addone('$ret', $ret);
        return $output;
    }

    public function exec ($pull=false)
    {
        if (!file_exists($this->gitlock) || $pull) {
            ob_start();
            // Setup SSH agent
            $this->execOps ( 'eval "$(ssh-agent -s)"' );
            
            // Add key
            $this->execOps ( "ssh-add ~/.ssh/id_rsa" );
            
            // check ssh auth sock
            $this->execOps ( 'echo "$SSH_AUTH_SOCK"' );
            
            // check github access
            $this->execOps ( 'ssh -T git@github.com' );
            
            // cd to $github_basedir
            $this->execOps ( "cd $this->github_basedir" );
            
            // git pull
            $this->execOps ( "git pull" );
            
            $output = ob_get_flush();
            
            Mlog::addone('output::',$output);
            
            //write lock file
            $file = fopen($this->gitlock,"w");
            echo fwrite($file,$output);
            fclose($file);
        }
    }
} // end class MemreasTranscoder