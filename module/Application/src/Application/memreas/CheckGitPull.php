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

    public function exec ($pull = false)
    {
        $pulled_latest = false;
        $output = '';
        if (file_exists($this->gitlock) && ! $pull) {
            $pulled_latest = true;
        } else 
            if (! file_exists($this->gitlock) || $pull) {
                // Setup SSH agent
                $output = $this->execOps('eval "$(ssh-agent -s)"');
                echo $output . PHP_EOL;
                
                // Add key
                $output = $this->execOps("ssh-add ~/.ssh/id_rsa");
                echo $output . PHP_EOL;
                
                // check ssh auth sock
                $output = $this->execOps('echo "$SSH_AUTH_SOCK"');
                echo $output . PHP_EOL;
                
                // check github access
                $output = $this->execOps('ssh -T git@github.com');
                echo $output . PHP_EOL;
                
                // cd to $github_basedir
                $output = $this->execOps("cd $this->github_basedir");
                echo $output . PHP_EOL;
                
                // remove composer.phar
                $output = $this->execOps("git reset --hard HEAD");
                echo $output . PHP_EOL;
                
                // git pull
                $output = $this->execOps("git pull");
                echo $output . PHP_EOL;
                
                // write lock file
                if (file_exists($this->gitlock)) {
                    $output = $this->execOps("rm " . $this->gitlock);
                }
                $file = fopen($this->gitlock, "w");
                echo fwrite($file, $output);
                fclose($file);
                
                // set permissions
                // $output = $this->execOps ( "git pull" );
                $pulled_latest = true;
                
                putenv("COMPOSER_HOME=" . $this->github_basedir);
                $output = $this->execOps("php composer.phar self-update");
                echo $output . PHP_EOL;
                $output = $this->execOps("php composer.phar update");
                echo $output . PHP_EOL;
                
                Mlog::addone('output::', $output);
            }
        return $output;
    }
} // end class MemreasTranscoder