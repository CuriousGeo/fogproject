<?php
class FOGFTP extends FOGGetSet {
	// Data
	public $data = array(
		'host'		=> '',
		'username'	=> '',
		'password'	=> '',
		'port'		=> '',
		'timeout'	=> '', 
	);
	// Links
	private $link;
	private $loginLink;
	private $lastConnectionHash;
	public $passiveMode = true;
	public function connect() {
		$this->set('port',$this->getClass('FOGCore')->getSetting('FOG_FTP_PORT'));
		$this->set('timeout',$this->getClass('FOGCore')->getSetting('FOG_FTP_TIMEOUT'));
		// Return if - already connected && last connection is the same || details unset
		$connectionHash = md5(serialize($this->data));
		if (($this->link && $this->lastConnectionHash == $connectionHash) || !$this->get('host') || !$this->get('username') || !$this->get('password') || !$this->get('port')) return $this;
		// Connect
		$this->link = @ftp_connect($this->get('host'), $this->get('port'), $this->get('timeout'));
		if (!$this->link) {
			$error = error_get_last();
			$result = sprintf('%s: Failed to connect. Host: %s, Error: %s', get_class($this), $this->get('host'), $error['message']);
		}
		// Login
		if (!$this->loginLink = @ftp_login($this->link, $this->get('username'), $this->get('password'))) {
			$error = error_get_last();
			$result = sprintf('%s: Login failed. Host: %s, Username: %s, Password: %s, Error: %s', get_class($this), $this->get('host'), $this->get('username'), $this->get('password'), $error['message']);
		}
		if ($this->passiveMode) ftp_pasv($this->link, true);
		// Store connection hash
		$this->lastConnectionHash = $connectionHash;
		// Return
		return ($error ? $result : $this);
	}
	public function close($if = true) {
		// Only if connected
		if ($this->link && $if) {
			// Disconnect
			@ftp_close($this->link);
			// unset connection variable
			unset($this->link);
		}
		// Return
		return $this;
	}
	public function put($remotePath, $localPath, $mode = FTP_BINARY) {
		// Put file
		if (!@ftp_put($this->link, $remotePath, $localPath, $mode)) {
			$error = error_get_last();
			throw new Exception(sprintf('%s: Failed to %s file. Remote Path: %s, Local Path: %s, Error: %s', get_class($this), __FUNCTION__, $remotePath, $localPath, $error['message']));
		}
		// Return
		return $this;
	}
	public function rename($remotePath, $localPath) {
		if(@ftp_nlist($this->link,$localPath)) {
			if(!@ftp_rename($this->link, $localPath, $remotePath)) {
				$error = error_get_last();
				throw new Exception(sprintf('%s: Failed to %s file. Remote Path: %s, Local Path: %s, Error: %s', get_class($this), __FUNCTION__, $remotePath, $localPath, $error['message']));
			}
		}
		return $this;
	}
	public function nlist($remotePath) {return @ftp_nlist($this->link,$remotePath);}
	public function chdir($path) {return @ftp_chdir($this->link, $path);}
	public function size($pathfile) {
		$size = 0;
		$filelist = @ftp_rawlist($this->link,$pathfile);
		if ($filelist) {
			foreach($filelist AS $file) {
				$fileinfo = preg_split('#\s+#',$file,null,PREG_SPLIT_NO_EMPTY);
				$size += $fileinfo[4];
			}
		}
		return ($size > 0 ? $size : 0);
	}
	public function mkdir($remotePath) {return @ftp_mkdir($this->link,$remotePath);}
	public function delete($path) {
		if (!(@ftp_delete($this->link, $path)||@ftp_rmdir($this->link,$path))) {
			$filelist = @ftp_nlist($this->link,$path);
			if ($filelist) {
				foreach($filelist AS $file) $this->delete($file);
				$this->delete($path);
			}
		}
		// Return
		return $this;
	}
}
