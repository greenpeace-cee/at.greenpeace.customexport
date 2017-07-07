<?php

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
    $this->url = $host;
    $this->remotePath = $path;
  }

  /**
   * The actual upload function.
   * @return int: This is the error code from curl, 0 is success!  Anything else is failure
   */
  function upload() {
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
  }

  function getErrorMessage() {
    return $this->error;
  }

}
