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
        $output = shell_exec($op . ' 2>&1') . PHP_EOL;
        return $output;
    }

    public function exec ($pull=false)
    {
        $pulled_latest = false;
        if (file_exists($this->gitlock)) {
            $pulled_latest = true;
        } else if (!file_exists($this->gitlock) || $pull) {
            // Setup SSH agent
            $output = $this->execOps ( 'eval "$(ssh-agent -s)"' );
            
            // Add key
            $output .= $this->execOps ( "ssh-add ~/.ssh/id_rsa" );
            
            // check ssh auth sock
            $output .= $this->execOps ( 'echo "$SSH_AUTH_SOCK"' );
            
            // check github access
            $output .= $this->execOps ( 'ssh -T git@github.com' );
            
            // cd to $github_basedir
            $output .= $this->execOps ( "cd $this->github_basedir" );
            
            // git pull
            $output .= $this->execOps ( "git pull" );
            
            Mlog::addone('output::',$output);
            
            //write lock file
            if (file_exists($this->gitlock)) {
                unlink($this->gitlock);
            }
            $file = fopen($this->gitlock,"w");
            echo fwrite($file,$output);
            fclose($file);
            $pulled_latest = true;
        }
        return $pulled_latest;
    }
} // end class MemreasTranscoder