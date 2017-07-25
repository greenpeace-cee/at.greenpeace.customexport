<?php
/*-------------------------------------------------------+
| SYSTOPIA - Custom Export for Greenpeace                |
| Copyright (C) 2017 SYSTOPIA                            |
| Author: M. Wire (mjw@mjwconsult.co.uk)                 |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

class CRM_Customexport_Upload {
  private $localFile;
  private $method;
  private $user;
  private $password;
  private $url;
  private $remotePath;
  private $error;

  /**
   * CRM_Customexport_Upload constructor.
   *
   * @param $localFile (pass full path and filename)
   * @param string $method (defaults to sftp)
   *
   * @throws \Exception
   */
  function __construct($localFile, $method='sftp') {
    if (!file_exists($localFile)) {
      throw new Exception ('File does not exist!');
    }
    $this->localFile = $localFile;
    $this->method = $method;
  }

  /**
   * Set user credentials for remote server (must be called before setServer)
   * @param $user
   * @param $password
   */
  function setCredentials($user, $password) {
    $this->user = $user;
    $this->password = $password;
  }

  /**
   * Set remote server url and path.  You can pass in a url like sftp://user:password@host.com or just host.com
   *  If you just pass in 'host.com' the method, username and password will be added automatically.
   * @param $host
   * @param $fullURI = TRUE.  If TRUE, full URI must be passed in via $host (eg. sftp://user:pass@example.com/dir1/).  Optionally specify filename too, or specify this in $path
   * @param $path
   */
  function setServer($host, $fullURI = TRUE, $path) {
    if (!$fullURI) {
      // Prefix host with uri.
      if (strpos($host, '://') === FALSE) {
        switch ($this->method) {
          case 'sftp':
          default:
            $host = 'sftp://' . $this->user . ':' . $this->password . '@' . $host;
        }
      }
      if (substr($host, -1) != '/') {
        $host = $host . '/';
      }
    }
    else {
      // If fullURI we need to break it up
      // FIXME: This could do with some error checking!
      $index = strpos($host, '://') + 3;
      $protocol = substr($host, 0, $index);
      $host = substr($host, $index);
      $index = strpos($host, '@');
      $userArr = explode(':', substr($host, 0, $index));
      $this->user = $userArr[0];
      $this->password = isset($userArr[1]) ? $userArr[1] : NULL;
      $host = substr($host, $index);
      $index = strpos($host, '/');
      if ($index !== FALSE) {
        $hostname = substr($host, 1, $index -1);
        $path2 = substr($host, $index);
        $path = $path2 . $path;
      }
    }
    $this->url = $hostname;
    $this->remotePath = $path;
  }

  /**
   * The actual upload function.
   * @return int: This is the error code from curl, 0 is success!  Anything else is failure
   */
  function upload() {
    require_once('Net/SFTP.php');

    define('NET_SFTP_LOGGING', NET_SFTP_LOG_SIMPLE);

    $sftp = new Net_SFTP($this->url);
    if (!$sftp->login($this->user, $this->password)) {
      $this->error = 'Login Failed';
      return 1;
    }

    // puts an x-byte file named filename.remote on the SFTP server,
    // where x is the size of filename.local
    $result = $sftp->put($this->remotePath, $this->localFile, NET_SFTP_LOCAL_FILE);
    $this->error = $sftp->getSFTPLog();
    if ($result) {
      return 0;
    }
    return 1;
  }

  /*  function upload() {
      $ch = curl_init();
      $localfile = $this->localFile;
      $fp = fopen($localfile, 'r');
      curl_setopt($ch, CURLOPT_URL, $this->url.$this->remotePath);
      curl_setopt($ch, CURLOPT_UPLOAD, 1);
      switch ($this->method) {
        case 'sftp':
        default:
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_SFTP);
      }
      curl_setopt($ch, CURLOPT_INFILE, $fp);
      curl_setopt($ch, CURLOPT_INFILESIZE, filesize($localfile));
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
      curl_exec ($ch);
      $error_no = curl_errno($ch);
      if ($error_no == 0) {
        $this->error = 'File uploaded succesfully.';
      } else {
        $this->error = curl_error($ch);
      }

      curl_close ($ch);

      return $error_no;
    }*/

  function getErrorMessage() {
    return $this->error;
  }

}
