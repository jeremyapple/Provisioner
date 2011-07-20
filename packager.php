<?PHP
/*l
This file, when run from the web, creates all the needed packages in the releases folder and also generates http://www.provisioner.net/releases
*/
//This is not for any 'scary' security measures, it's just so I can prevent robots from running the script all the time.
if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header('WWW-Authenticate: Basic realm="My Realm"');
    header('HTTP/1.0 401 Unauthorized');
	die('no');
} else {
	if(($_SERVER['PHP_AUTH_USER'] != 'maint') && ($_SERVER['PHP_AUTH_PW'] != 'maint')) {
		die('no');
	}
}

set_time_limit(0);
define("MODULES_DIR", "/var/www/html/repo/endpoint");
define("RELEASE_DIR", "/var/www/html/release3");
define("ROOT_DIR", "/var/www/html/repo");
define("FIRMWARE_DIR", "/var/www/html/repo_firmwares");

echo "======PROVISIONER.NET REPO MAINTENANCE SCRIPT======\n\n\n\n";

$supported_phones = array();
$master_xml = array();

echo "<pre>";

if(isset($_REQUEST['commit_message'])) {
	$c_message = $_REQUEST['commit_message'];
} else {
	$c_message = "PACKAGER: ".file_get_contents('/var/www/html/c_message.txt');
}
if(!isset($_REQUEST['dont_push'])) {
	echo "===GIT Information===\n";
	echo "COMMIT MESSAGE: ".$c_message."\n";
	echo "Pulling GIT Master Repo......\n";
	exec("git pull origin master", $output);
	echo "GIT REPORTED: \n";
	foreach($output as $data) {
		echo "\t".$data . "\n";
	}
	echo "Revision information is as follows: " . file_get_contents(ROOT_DIR . "/.git/FETCH_HEAD");
	echo "=====================\n\n";
}

echo "Starting Processing of Directories\n";

foreach (glob(MODULES_DIR."/*", GLOB_ONLYDIR) as $filename) {
	flush_buffers();
    if(file_exists($filename."/brand_data.xml")) {
		$brand_xml = xml2array($filename."/brand_data.xml");
		$old_brand_timestamp = $brand_xml['data']['brands']['last_modified'];
		echo "==============".$brand_xml['data']['brands']['name']."==============\n";
		echo "Found brand_data.xml in ". $filename ." continuing...\n";
		echo "\tAttempting to parse data into array....";
		$excludes = "";
		flush_buffers();
		if(!empty($brand_xml)) {
			if(!empty($brand_xml['data']['brands']['brand_id'])) {
				echo "Looks Good...Moving On\n";
				$key = $brand_xml['data']['brands']['brand_id'];
				$master_xml['brands'][$key]['name'] =  $brand_xml['data']['brands']['name'];
				$master_xml['brands'][$key]['directory'] =  $brand_xml['data']['brands']['directory'];
				create_brand_pkg($master_xml['brands'][$key]['directory'],$brand_xml['data']['brands']['version'],$brand_xml['data']['brands']['name'],$old_brand_timestamp,$c_message);
			} else {
				echo "\n\tError with the XML in file (brand_id is blank?): ". $filename."/brand_data.xml";
			}
		} else {
			echo "\n\tError with the XML in file: ". $filename."/brand_data.xml";
		}
		echo "\n\n";
	}
}

copy(ROOT_DIR."/autoload.php",ROOT_DIR."/setup.php");
$endpoint_max[0] = filemtime(ROOT_DIR."/autoload.php");
$endpoint_max[1] = filemtime(MODULES_DIR."/base.php");

$endpoint_max = max($endpoint_max);

exec("tar zcf ".RELEASE_DIR."/provisioner_net.tgz --exclude .svn -C ".ROOT_DIR."/ setup.php endpoint/base.php");

unlink(ROOT_DIR."/setup.php");

$html = "======= Provisioner.net Library Releases ======= \n == Note: This page is edited by an outside script and can not be edited == \n Latest Commit Message: //".$c_message."//\n<html>";

$fp = fopen(MODULES_DIR.'/master.xml', 'w');
$data = "";
$data .= "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<!--This File is Auto Generated by the Publish Script! -->\n<data>";

$data .= "\n\t<last_modified>".$endpoint_max."</last_modified>";
$data .= "\n\t<package>provisioner_net.tgz</package>";

$html .= "<hr><h3>Provisoner.net Package (Last Modified: ".date('m/d/Y',$endpoint_max)." at ".date("G:i",$endpoint_max).")</h3>";
$html .= "<a href='/release3/provisioner_net.tgz'>provisioner_net.tgz</a>";

foreach($master_xml['brands'] as $master_list) {
	$data .= "\n\t<brands>";
	
	$data .= "\n\t\t<name>".$master_list['name']."</name>";
	$data .= "\n\t\t<directory>".$master_list['directory']."</directory>";
	
	$data .= "\n\t</brands>";	
}

$data .= "\n</data>";

fwrite($fp, $data);
fclose($fp);

copy(MODULES_DIR."/master.xml", RELEASE_DIR."/master.xml");

$html .= "<hr><h3>Master List File</h3>";
$html .= "<a href='/release3/master.xml'>master.xml</a>";

$html .= "<hr><h3>Brand Packages</h3>".$brands_html;

$html .= "</html>";
$fp = fopen('/var/www/data/pages/releases3.txt', 'w');
fwrite($fp, $html);
fclose($fp);

$fp = fopen('/var/www/data/pages/supported.txt', 'w');
$html2 = "=======This is the list of Supported Phones======= \n == Note: This page is edited by an outside script and can not be edited == \n";

array_multisort($supported_phones);

foreach($supported_phones as $key => $data2) {
	$html2 .= "==".$key."==\n";
	foreach($data2 as $data) {
		foreach($data as $more_data) {
			$html2 .= "\t*".$more_data."\n";
		}
	}
}
fwrite($fp, $html2);
fclose($fp);

if(!isset($_REQUEST['dont_push'])) {
	echo "===GIT Information===\n";

	echo "Running Git Add, Status:\n";
	exec("git add .",$output);
	foreach($output as $data) {
		echo "\t".$data . "\n";
	}

	echo "Running Git Delete, Status:\n";
	exec("git add -u",$output);
	foreach($output as $data) {
		echo "\t".$data . "\n";
	}

	echo "Running Git Commit, Status:\n";
	exec('git commit -m "'.$c_message.'"',$output);
	foreach($output as $data) {
		echo "\t".$data . "\n";
	}

	echo "Running Git Push, Status:\n";
	exec("git push",$output);
	foreach($output as $data) {
		echo "\t".$data . "\n";
	}

	echo "=====================\n\n";
}
if(!isset($_REQUEST['dont_push'])) {
	file_put_contents('/var/www/html/sync_check', '1');
}

echo "\nDone!";

/************
* FUNCTIONS ONLY BELOW HERE!
*
*
*
*************/

function fix_single_array_keys($array) {
	if((empty($array[0])) AND (!empty($array))) {
		$array_n[0] = $array;
		return($array_n);
	} elseif(!empty($array)) {
		return($array);
	} else {
		return("");
	}	
}

function create_brand_pkg($rawname,$version,$brand_name,$old_brand_timestamp,$c_message) {	
	global $brands_html, $supported_phones;
	$version = str_replace(".","_",$version);
	
	$pkg_name = $rawname . "-" . $version;
	
	if(!file_exists(RELEASE_DIR."/".$rawname)) {
		mkdir(RELEASE_DIR."/".$rawname);
		
	}
	$family_list = "\n<!--Below is Auto Generated-->";
	$z = 0;	
	foreach (glob(MODULES_DIR."/".$rawname."/*", GLOB_ONLYDIR) as $family_folders) {
		flush_buffers();
		if(file_exists($family_folders."/family_data.xml")) {
			$family_xml = xml2array($family_folders."/family_data.xml");
			$old_firmware_ver = $family_xml['data']['firmware_ver'];
			echo "\n\t==========".$family_xml['data']['name']."==========\n";
			echo "\tFound family_data.xml in ". $family_folders ."\n";

			$b = 0;
			foreach($family_xml['data']['model_list'] as $data) {
				$supported_phones[$brand_name][$z][$b] = $data['model'];
				$b++;
			}
			
			$i=0;
			
			$dir_iterator = new RecursiveDirectoryIterator($family_folders."/");
			$iterator = new RecursiveIteratorIterator($dir_iterator, RecursiveIteratorIterator::SELF_FIRST);
			
			foreach ($iterator as $family_files) {
				if((!is_dir($family_files)) && (dirname($family_files) != $family_folders."/firmware") && (dirname($family_files) != $family_folders."/json")) {
					$path_parts = pathinfo($family_files);
					if((basename($family_files) != "family_data.xml") AND ($path_parts['extension'] != "json")) {
						$files_array[$i] = filemtime($family_files);
						echo "\t\tParsing File: ".basename($family_files)."|".$files_array[$i]."\n";
						$i++;
					}
				} 
			}
			
			$family_max = max($files_array);
			$family_max_array[$z] = $family_max;
			echo "\t\t\tTotal Family Timestamp: ". $family_max ."\n";
			
			if(file_exists(FIRMWARE_DIR."/".$rawname."/".$family_xml['data']['directory']."/firmware")) {		
				echo "\t\tFound Firmware Folder in ".$family_xml['data']['directory']."\n";
				flush_buffers();
				$x=0;
				foreach (glob(FIRMWARE_DIR."/".$rawname."/".$family_xml['data']['directory']."/firmware/*") as $firmware_files) {
					flush_buffers();
					if(!is_dir($firmware_files)) {
						$firmware_files_array[$x] = filemtime($firmware_files);
						echo "\t\t\t\tParsing File: ".basename($firmware_files)."|".$firmware_files_array[$x]."\n";
						$x++;
					}
				}
				
				$firmware_max = max($firmware_files_array);
                echo "\t\t\t\t\tTotal Firmware Timestamp: ". $firmware_max ."\n";

				if($firmware_max != $old_firmware_ver) {
					echo "\t\t\tFirmware package has changed...\n";
					echo "\t\t\tCreating Firmware Package\n";
					exec("tar zcf ".RELEASE_DIR."/".$rawname."/".$family_xml['data']['directory']."_firmware.tgz --exclude .svn -C ".FIRMWARE_DIR."/".$rawname."/".$family_xml['data']['directory']." firmware");
					$firmware_md5 = md5_file(RELEASE_DIR."/".$rawname."/".$family_xml['data']['directory']."_firmware.tgz");
				
					echo "\t\t\tPackage MD5 SUM: ".$firmware_md5."\n";
				
					echo "\t\t\tAdding Firmware Package Information to family_data.xml File\n";
				
					if($firmware_max > $family_max) {
						echo "\t\t\tFirmware Timestamp is newer than Family Timestamp, updating Family Timestamp to match\n";
						$family_max = $firmware_max;
						$family_max_array[$z] = $family_max;
					}
                
					$fp = fopen($family_folders."/family_data.xml", 'r');
					$contents = fread($fp, filesize($family_folders."/family_data.xml"));
					fclose($fp);
				
					$pattern = "/<firmware_ver>(.*?)<\/firmware_ver>/si";
					$parsed = "<firmware_ver>".$firmware_max."</firmware_ver>";
					$contents = preg_replace($pattern, $parsed, $contents, 1);
				
					$pattern = "/<firmware_md5sum>(.*?)<\/firmware_md5sum>/si";
					$parsed = "<firmware_md5sum>".$firmware_md5."</firmware_md5sum>";
					$contents = preg_replace($pattern, $parsed, $contents, 1);

					$pattern = "/<firmware_pkg>(.*?)<\/firmware_pkg>/si";
					$parsed = "<firmware_pkg>".$family_xml['data']['directory']."_firmware.tgz</firmware_pkg>";
					$contents = preg_replace($pattern, $parsed, $contents, 1);
			
					$fp = fopen($family_folders."/family_data.xml", 'w');
					fwrite($fp, $contents);
					fclose($fp);
				} else {
					echo "\t\t\tFirmware has not changed, not updating package\n";
				}
			}
			
			$z++;
			
			echo "\tComplete..Continuing..\n";

			$description = fix_single_array_keys($family_xml['data']['description']);

			$family_list .= "
			<family>
				<name>".$family_xml['data']['name']."</name>
				<directory>".$family_xml['data']['directory']."</directory>
				<version>".$family_xml['data']['version']."</version>
				<description>".$description."</description>
				<changelog>".fix_single_array_keys($family_xml['data']['changelog'])."</changelog>
				<id>".$family_xml['data']['id']."</id>
				<last_modified>".$family_max."</last_modified>
			</family>";
	    }
	}
	$family_list .= "\n<!--End Auto Generated-->\n";
	
	echo "\n\t==========".$brand_name."==========\n";
	echo "\tCreating Completed Package\n";
	
	$fp = fopen(MODULES_DIR."/".$rawname."/brand_data.xml", 'r');
	$contents = fread($fp, filesize(MODULES_DIR."/".$rawname."/brand_data.xml"));
	fclose($fp);
	
	$pattern = "/<family_list>(.*?)<\/family_list>/si";
	$parsed = "<family_list>".$family_list."\n\t\t</family_list>";
	$contents = preg_replace($pattern, $parsed, $contents, 1);
	
	$pattern = "/<package>(.*?)<\/package>/si";
	$parsed = "<package>".$pkg_name.".tgz</package>";
	$contents = preg_replace($pattern, $parsed, $contents, 1);
	
	$i=0;
	foreach (glob(MODULES_DIR."/".$rawname."/*") as $brand_files) {
		if((!is_dir($brand_files)) AND (basename($brand_files) != "brand_data.xml") AND (basename($brand_files) != "brand_data.json")) {
			$brand_files_array[$i] = filemtime($brand_files);
			echo "\t\tParsing File: ".basename($brand_files)."|".$brand_files_array[$i]."\n";
			$i++;
		}
	}
	$brand_max = max($brand_files_array);
	$temp = max($family_max_array);
	$brand_max = max($brand_max,$temp);
	echo "\t\t\tTotal Brand Timestamp: ".$brand_max."\n";
	
	if($brand_max != $old_brand_timestamp) {
		$pattern = "/<last_modified>(.*?)<\/last_modified>/si";
		$parsed = "<last_modified>".$brand_max."</last_modified>";
		$contents = preg_replace($pattern, $parsed, $contents, 1);
	
		$fp = fopen(MODULES_DIR."/".$rawname."/brand_data.xml", 'w');
		fwrite($fp, $contents);
		fclose($fp);
	
		copy(MODULES_DIR."/".$rawname."/brand_data.xml", RELEASE_DIR."/".$rawname."/".$rawname.".xml");
	
		exec("tar zcf ".RELEASE_DIR."/".$rawname."/".$pkg_name.".tgz --exclude .svn --exclude firmware -C ".MODULES_DIR." ".$rawname);
		$brand_md5 = md5_file(RELEASE_DIR."/".$rawname."/".$pkg_name.".tgz");
		echo "\t\tPackage MD5 SUM: ".$brand_md5."\n";
	
		$fp = fopen(MODULES_DIR."/".$rawname."/brand_data.xml", 'r');
		$contents = fread($fp, filesize(MODULES_DIR."/".$rawname."/brand_data.xml"));
		fclose($fp);
	
		$pattern = "/<md5sum>(.*?)<\/md5sum>/si";
		$parsed = "<md5sum>".$brand_md5."</md5sum>";
		$contents = preg_replace($pattern, $parsed, $contents, 1);
		
		$pattern = "/<changelog>(.*?)<\/changelog>/si";
		$parsed = "<changelog>".$c_message."</changelog>";
		$contents = preg_replace($pattern, $parsed, $contents, 1);
	
		$fp = fopen(MODULES_DIR."/".$rawname."/brand_data.xml", 'w');
		fwrite($fp, $contents);
		fclose($fp);
	
		copy(MODULES_DIR."/".$rawname."/brand_data.xml", RELEASE_DIR."/".$rawname."/".$rawname.".xml");
	
		$brands_html .= "<h4>".$rawname." (Last Modified: ".date('m/d/Y',$brand_max)." at ".date("G:i",$brand_max).")</h4>";
		$brands_html .= "XML File: <a href='/release3/".$rawname."/".$rawname.".xml'>".$rawname.".xml</a><br/>";
		$brands_html .= "Package File: <a href='/release3/".$rawname."/".$pkg_name.".tgz'>".$pkg_name.".tgz</a><br/>";
		
		echo "\tComplete..Continuing..\n";
	} else {
		echo "\tNothing changed! Aborting Package Creation!\n";
	}
}


function flush_buffers(){
    ob_end_flush();
    //ob_flush();
    flush();
    ob_start();
}

function xml2array($url, $get_attributes = 1, $priority = 'tag')
{
    $contents = "";
    if (!function_exists('xml_parser_create'))
    {
        return array ();
    }
    $parser = xml_parser_create('');
    if (!($fp = @ fopen($url, 'rb')))
    {
        return array ();
    }
    while (!feof($fp))
    {
        $contents .= fread($fp, 8192);
    }
    fclose($fp);
    xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, "UTF-8");
    xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
    xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
    xml_parse_into_struct($parser, trim($contents), $xml_values);
    xml_parser_free($parser);
    if (!$xml_values)
        return; //Hmm...
    $xml_array = array ();
    $parents = array ();
    $opened_tags = array ();
    $arr = array ();
    $current = & $xml_array;
    $repeated_tag_index = array (); 
    foreach ($xml_values as $data)
    {
        unset ($attributes, $value);
        extract($data);
        $result = array ();
        $attributes_data = array ();
        if (isset ($value))
        {
            if ($priority == 'tag')
                $result = $value;
            else
                $result['value'] = $value;
        }
        if (isset ($attributes) and $get_attributes)
        {
            foreach ($attributes as $attr => $val)
            {
                if ($priority == 'tag')
                    $attributes_data[$attr] = $val;
                else
                    $result['attr'][$attr] = $val; //Set all the attributes in a array called 'attr'
            }
        }
        if ($type == "open")
        { 
            $parent[$level -1] = & $current;
            if (!is_array($current) or (!in_array($tag, array_keys($current))))
            {
                $current[$tag] = $result;
                if ($attributes_data)
                    $current[$tag . '_attr'] = $attributes_data;
                $repeated_tag_index[$tag . '_' . $level] = 1;
                $current = & $current[$tag];
            }
            else
            {
                if (isset ($current[$tag][0]))
                {
                    $current[$tag][$repeated_tag_index[$tag . '_' . $level]] = $result;
                    $repeated_tag_index[$tag . '_' . $level]++;
                }
                else
                { 
                    $current[$tag] = array (
                        $current[$tag],
                        $result
                    ); 
                    $repeated_tag_index[$tag . '_' . $level] = 2;
                    if (isset ($current[$tag . '_attr']))
                    {
                        $current[$tag]['0_attr'] = $current[$tag . '_attr'];
                        unset ($current[$tag . '_attr']);
                    }
                }
                $last_item_index = $repeated_tag_index[$tag . '_' . $level] - 1;
                $current = & $current[$tag][$last_item_index];
            }
        }
        elseif ($type == "complete")
        {
            if (!isset ($current[$tag]))
            {
                $current[$tag] = $result;
                $repeated_tag_index[$tag . '_' . $level] = 1;
                if ($priority == 'tag' and $attributes_data)
                    $current[$tag . '_attr'] = $attributes_data;
            }
            else
            {
                if (isset ($current[$tag][0]) and is_array($current[$tag]))
                {
                    $current[$tag][$repeated_tag_index[$tag . '_' . $level]] = $result;
                    if ($priority == 'tag' and $get_attributes and $attributes_data)
                    {
                        $current[$tag][$repeated_tag_index[$tag . '_' . $level] . '_attr'] = $attributes_data;
                    }
                    $repeated_tag_index[$tag . '_' . $level]++;
                }
                else
                {
                    $current[$tag] = array (
                        $current[$tag],
                        $result
                    ); 
                    $repeated_tag_index[$tag . '_' . $level] = 1;
                    if ($priority == 'tag' and $get_attributes)
                    {
                        if (isset ($current[$tag . '_attr']))
                        { 
                            $current[$tag]['0_attr'] = $current[$tag . '_attr'];
                            unset ($current[$tag . '_attr']);
                        }
                        if ($attributes_data)
                        {
                            $current[$tag][$repeated_tag_index[$tag . '_' . $level] . '_attr'] = $attributes_data;
                        }
                    }
                    $repeated_tag_index[$tag . '_' . $level]++; //0 and 1 index is already taken
                }
            }
        }
        elseif ($type == 'close')
        {
            $current = & $parent[$level -1];
        }
    }
    return ($xml_array);
}
?>