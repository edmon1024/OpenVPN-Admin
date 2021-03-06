<?php
  session_start();
  
  require(dirname(__FILE__) . '/include/functions.php');
  require(dirname(__FILE__) . '/include/connect.php');
  
  // Disconnecting ?
  if(isset($_GET['logout'])){
    session_destroy();
    header("Location: .");
    exit -1;
  }
  
  // Get the configuration files ?
  if(isset($_POST['configuration_get'], $_POST['configuration_username'], $_POST['configuration_pass'], $_POST['configuration_os'])
     && !empty($_POST['configuration_pass'])) {
    $req = $bdd->prepare('SELECT * FROM user WHERE user_id = ?');
    $req->execute(array($_POST['configuration_username']));
    $data = $req->fetch();
    
    // Error ?
    if($data && passEqual($_POST['configuration_pass'], $data['user_pass'])) {
      // Thanks http://stackoverflow.com/questions/4914750/how-to-zip-a-whole-folder-using-php
      if($_POST['configuration_os'] == "gnu_linux") {
        $conf_dir = 'gnu-linux';
      }
      else {
        $conf_dir = 'windows';
      }
      $rootPath = realpath("./client-conf/$conf_dir");
      
      // Initialize archive object
      $archive_name = "openvpn-$conf_dir.zip";
      $archive_path = "./client-conf/$archive_name";
      $zip = new ZipArchive();
      $zip->open($archive_path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
      
      $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($rootPath),
        RecursiveIteratorIterator::LEAVES_ONLY
      );
      
      foreach ($files as $name => $file) {
        // Skip directories (they would be added automatically)
        if (!$file->isDir()) {
          // Get real and relative path for current file
          $filePath = $file->getRealPath();
          $relativePath = substr($filePath, strlen($rootPath) + 1);
  
          // Add current file to archive
          $zip->addFile($filePath, $relativePath);
        }
      }
      
      // Zip archive will be created only after closing object
      $zip->close();
      
      //then send the headers to foce download the zip file
      header("Content-type: application/zip"); 
      header("Content-Disposition: attachment; filename=$archive_name"); 
      header("Pragma: no-cache"); 
      header("Expires: 0"); 
      readfile($archive_path);
    }
    else {
      $error = true;
    }
  }
  
  // Admin login attempt ?
  else if(isset($_POST['admin_login'], $_POST['admin_username'], $_POST['admin_pass']) && !empty($_POST['admin_pass'])){
    
    $req = $bdd->prepare('SELECT * FROM admin WHERE admin_id = ?');
    $req->execute(array($_POST['admin_username']));
    $data = $req->fetch();
    
    // Error ?
    if($data && passEqual($_POST['admin_pass'], $data['admin_pass'])) {
      $_SESSION['admin_id'] = $data['admin_id'];
      header("Location: index.php?admin");
      exit -1;
    }
    else {
      $error = true;
    }
  }
?>

<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8" />
    
    <link rel="stylesheet" href="vendor/bootstrap/dist/css/bootstrap.min.css" type="text/css" />
    <link rel="stylesheet" href="css/index.css" type="text/css"/>
    
    <link rel="stylesheet" href="vendor/slickgrid/slick.grid.css" type="text/css" />
    <link rel="stylesheet" href="vendor/slickgrid/slick-default-theme.css" type="text/css" />
    <link rel="stylesheet" href="vendor/slickgrid/css/smoothness/jquery-ui-1.8.16.custom.css" type="text/css" />
    <link rel="stylesheet" href="vendor/slickgrid-enhancement-pager/libs/dropkick.css" type="text/css" />
    <link rel="stylesheet" href="vendor/slickgrid-enhancement-pager/libs/enhancementpager.css" type="text/css" />
    
  </head>
  <body class='container-fluid'>
  <?php
  
    // --------------- INSTALLATION ---------------
    if(isset($_GET['installation'])) {
      if(isInstalled($bdd) == true) {
        printError('OpenVPN-admin is already installed.');
        exit -1;
      }
      
      // If the user sent the installation form
      if(isset($_POST['admin_username'])) {
        $admin_username = $_POST['admin_username'];
        $admin_pass = $_POST['admin_pass'];
        $admin_repeat_pass = $_POST['repeat_admin_pass'];
        
        if($admin_pass != $admin_repeat_pass) {
          printError('The passwords do not correspond.');
          exit -1;
        }
        
        // Create the tables or die
        $sql_file = dirname(__FILE__) . '/sql/import.sql';
        try {
          $sql = file_get_contents($sql_file);
          $bdd->exec($sql);
        }
        catch (PDOException $e) {
          printError($e->getMessage());
          exit -1;
        }
        
        // Generate the hash
        $hash_pass = hashPass($admin_pass);
        
        // Insert the new admin
        $req = $bdd->prepare('INSERT INTO admin (admin_id, admin_pass) VALUES (?, ?)');
        $req->execute(array($admin_username, $hash_pass));
        
        unlink($sql_file);
        rmdir(dirname(__FILE__) . '/sql');
        printSuccess('Well done, OpenVPN-Admin is installed.');
      }
      // Print the installation form
      else {
        require(dirname(__FILE__) . '/include/html/menu.php');
        require(dirname(__FILE__) . '/include/html/form/installation.php');
      }
      
      exit -1;
    }
    
    // --------------- CONFIGURATION ---------------
    if(!isset($_GET['admin'])) {
      if(isset($error) && $error == true)
        printError('Login error');
        
      require(dirname(__FILE__) . '/include/html/menu.php');
      require(dirname(__FILE__) . '/include/html/form/configuration.php');
    }
    
    
    // --------------- LOGIN ---------------
    else if(!isset($_SESSION['admin_id'])){
      if(isset($error) && $error == true)
        printError('Login error');
      
      require(dirname(__FILE__) . '/include/html/menu.php');
      require(dirname(__FILE__) . '/include/html/form/login.php');
    }
    
    // --------------- GRIDS ---------------
    else{
  ?>
      <nav class="navbar navbar-default">
        <p class="navbar-text">Signed as <?php echo $_SESSION['admin_id']; ?> / 
          <a class="navbar-link" href="index.php?logout" title="Logout ?">logout ?</a>
        </p>
      </nav>
      
  <?php
      require(dirname(__FILE__) . '/include/html/grids.php');
    }
  ?>
  </body>
</html>
