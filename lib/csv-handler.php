<?php
namespace SonjaBroda;

use Str;
use Exception;
use Yaml;

// geolocating
use Kirby\Geo;

class CsvHandler {

  private $items = array();
  private $file;
  private $parse_header;
  private $header;
  private $delimiter;
  private $length;

  function __construct($filepath, $parse_header = false, $delimiter = ',', $length='8000') {

    if(file_exists($filepath)) {

      $this->file = fopen($filepath, "r");
      $this->parse_header = $parse_header;
      $this->delimiter = $delimiter;
      $this->length = $length;

      if ($this->parse_header) {
        $this->header = fgetcsv($this->file, $this->length, $this->delimiter);
      }
    } else {

      throw new Exception('The file does not exist');

    }

  }


  function getHeader() {
    if($this->header) {
      return $this->header;
    } else {
      return false;
    }
  }

  function __destruct() {
    if ($this->file) {
      fclose($this->file);
    }
  }

  function getItems($maxLines=0) {

    //if $maxLines is set to 0, then get all the data

    $data = array();

    if ($maxLines > 0)
    $lineCount = 0;
    else
    $lineCount = -1; // so loop limit is ignored

    // flag for adding institution field back at end, if exists
    $hasInstitution = false;

    while ($lineCount < $maxLines && ($row = fgetcsv($this->file, $this->length, $this->delimiter)) !== false) {

      if ($this->parse_header) {
        // init institutions structure field, only if in imported CSV
        $institutions = ''; // so not undefined 
        $press = ''; // so not undefined 
        $footnotes = ''; // so not undefined 
        // placehold for geolocation loop
        $city = '';
        foreach ($this->header as $i => $heading_i) {

            if (strpos($heading_i, 'Title') !== false) {
                // convert footmark to apostrophe
                $projectTitle = $row[$i];
            }

            // H4All exceptions, but wont work b/c check for key later
            // if($heading_i == 'Inst1 Name') {
            // If an institute...
            if (strpos($heading_i, 'Inst') !== false) {
                // now can add this back at end
                $hasInstitution = true;


                // get number right after "Inst"
                // $institutionVersion = substr($heading_i, strpos($heading_i, "Inst") + strlen("Inst"), 1);    

                // do name
                if(strpos($heading_i, 'Name') !== false && '' != $row[$i]) {
                    // do Institutions and Press as structured items
                    $institutions = $institutions . PHP_EOL . '-' . PHP_EOL . '  institute: ' . $row[$i] . PHP_EOL;
                } elseif(strpos($heading_i, 'City') !== false && '' != $row[$i]) {
                    $institutions = $institutions . '  location: |' . PHP_EOL;
                    $institutions = $institutions . '    address: ' . $row[$i];              
                    $city = $row[$i]; // save for geolocating
                } elseif(strpos($heading_i, 'State') !== false && '' != $row[$i]) {
                    $institutions = $institutions . ', ' . $row[$i] . PHP_EOL;
                    // get lat and lng from google
                    // uses geo-plugin, from https://github.com/getkirby-plugins/geo-plugin
                    $getLocationOn = true;
                    if($getLocationOn) {
                        // check for placeholder institutions
                        if('' != $city && '' != $row[$i]) {
                            $latitude = geo::locate($city . ', ' . $row[$i])->lat();
                            $longitude = geo::locate($city . ', ' . $row[$i])->lng();

                            // throw new Exception("location didn't work");
                            // add lat and lng to yml
                            $institutions = $institutions . '    lat: "' . $latitude . '"' . PHP_EOL;
                            $institutions = $institutions . '    lng: "' . $longitude . '"' . PHP_EOL;
                        }                        
                    }
                } elseif(strpos($heading_i, 'Zip') !== false && '' != $row[$i]) {
                    $institutions = $institutions . '    zoom: "9"' . PHP_EOL;
                    $institutions = $institutions . '  postalcode: ' . $row[$i] . PHP_EOL;
                } elseif(strpos($heading_i, 'Type 1') !== false && '' != $row[$i]) {
                    $institutions = $institutions . '  institution_type_1: ' . $row[$i] . PHP_EOL;
                } elseif(strpos($heading_i, 'Type 2') !== false && '' != $row[$i]) {
                    $institutions = $institutions . '  institution_type_2: ' . $row[$i];
                }

            // Press
            } elseif (strpos($heading_i, 'Press') !== false) {
                // now can add this back at end
                $hasPress = true;

                // get number right after "Inst"
                // $institutionVersion = substr($heading_i, strpos($heading_i, "Inst") + strlen("Inst"), 1);    

                // do name
                if(strpos($heading_i, 'Text') !== false) {
                    // do Institutions and Press as structured items
                    $press = $press . PHP_EOL . '-' . PHP_EOL . '  text: ' . $row[$i] . PHP_EOL;
                } elseif(strpos($heading_i, 'Link') !== false) {
                    $press = $press . '  link: ' . $row[$i];              
                }


            // Footnotes
            } elseif (strpos($heading_i, 'Footnotes') !== false) {
                // now can add this back at end
                $hasFootnotes = true;

                // get number right after "Inst"
                // $institutionVersion = substr($heading_i, strpos($heading_i, "Inst") + strlen("Inst"), 1);    

                // do name
                if(strpos($heading_i, 'Text') !== false) {
                    // do Institutions and Press as structured items
                    $footnotes = $footnotes . PHP_EOL . '-' . PHP_EOL . '  text: ' . $row[$i];
                }

            } else {
                // regular, skip id field because in Kirby, id is title slugified
                if($heading_i != 'ID') {
                    // convert footmark to apostrophe
                    $cleanData = str_replace("'", "’", $row[$i]);
                    // save
                    $row_new[$heading_i] = $cleanData;
                    // $row_new[$heading_i] = $cleanData . '|' . $heading_i;
                }
            }
        }

        // after collected all data, add institution structure to data, if exists
        if($hasInstitution) {
            $row_new['Institutions'] = PHP_EOL . $institutions;
        }
        // after collected all data, add press structure to data, if exists
        if($hasPress) {
            $row_new['Press'] = PHP_EOL . $press;
        }
        // after collected all data, add press structure to data, if exists
        if(isset($hasFootnotes) && $hasFootnotes) {
            $row_new['Footnotes'] = PHP_EOL . $footnotes;
        }

        $data[] = $row_new;
      } else {
        $data[] = $row;
      }

      if ($maxLines > 0)
      $lineCount++;
    }

    return $data;
  }


  public function createPages($parent, $UIDKey, $template = 'default', $update = false) {

    $messages = array();

    if(is_a($parent, 'Page')) {
      $page = $parent;
    } else {
      $page = page($parent);
    }

    if($page) {
      // fetch items from CSV file
      $items = $this->getItems();

      foreach($items as $item) {

        $data = $item;


        // check if the index $UIDKey exists

        if(isset($item[$UIDKey])) {

          // Check if $UIDKey starts with a number
          if(ctype_digit(substr($UIDKey, 0, 1))) {
            $UIDKey = '_' . $UIDKey;
          }

            // convert footnote to dash, similar to kirby creation, from 
            // str::slug seems to remove certain things
            // $dashTitle = str_replace(str_split("\\/:'’*<>|"), "-", $item[$UIDKey]);
            $folderName = str::slug($item[$UIDKey]);
            // $dashTitle = str::slug($item[$UIDKey], '-', 'a-z0-9@._-');
            // $folderName = $dashTitle;

        } else {
          throw new Exception("The index does not exist");
        }

        if(page($parent)->children()->findBy('uid', $folderName)) {

          if($update) {

            // don't update title field, so remove it from $data to update
            unset($data[$UIDKey]);

            try {

              page($parent)->children()->findBy('uid', $folderName)->update($data);
              $messages[] = 'Success: ' . $folderName . ' was updated';

              } catch(Exception $e) {

                $messages[] = 'Error: ' . $folderName . ' ' . $e->getMessage();

              }

          } else {

            $messages[] = "The page " . $folderName . " already exists and may not be updated";

          }

        } else {

          // otherwise, create a new page
          try {

            $newPage = page($parent)->children()->create($folderName, $template, $data);
            $messages[] = 'Success: ' . $folderName . ' was created';

          } catch(Exception $e) {

            $messages[] = 'Error: ' . $folderName . ' ' . $e->getMessage();

          }
        }

      }

    } else {

      throw new Exception("The parent page does not exist.");

    }
    if(!empty($messages)) {
      $html = '';
      foreach($messages as $message) {
        $html .= '<div>' . $message . '</div>';
      }
      echo $html;
    }

  }

  public function createStructure($uri, $field, $append = false) {

    if(is_a($uri, 'Page')) {

      $page = $uri;

    } else {

      $page = page($uri);

    }

    if($page) {

      $items = $this->getItems();

      if($append === false) {

        $data = yaml::encode($items);

      } else {

        $data = $page->$field()->yaml();

        foreach($items as $item) {

          $data[] = $item;

        }

        $data = yaml::encode($data);

      }
        try {

          page($page)->update(array($field => $data));
          $messages[] = 'Success: The field "' . $field . '" was created/updated';

        } catch(Exception $e) {

          $messages[] = 'Error: The field "' . $field . '" could not be created/updated';

        }
    } else {

        $messages[] = " Error: The page does not exist";

    }
    if(!empty($messages)) {
      $html = '';
      foreach($messages as $message) {
        $html .= '<div>' . $message . '</div>';
      }
      echo $html;
    }
  }

  public function createUsers() {

    $_users = $this->getItems();

    $users = array();
    foreach($_users as $user) {
      $userName =  str::lower($user['Firstname'] . '-' . $user['Lastname']);
      $user['firstName'] = $user['Firstname'];
      $user['emails'] = $user['Email'];
      $user['username'] = $userName;
      $user['password'] = str_rot13($userName);
      $users[] = $user;
    }
    foreach($users as $key => $user) {

      try {

        $newUser = kirby()->site()->users()->create($user);

        $messages[] = 'User “'. $user['username'] .'” has been created.';
        //$response['counterSuccess'] ++;
      } catch(Exception $e) {

        try {

          $isUser = kirby()->site()->user($user['username'])->update($user);
          $messages[] = 'User “'. $user['username'] .'” has been updated.';
          //$response['counterUpdate'] ++;

        } catch(Exception $e) {

          $messages[] = 'User “'. $user['username'] .'” could not be created nor updated:' . "\n" . $e->getMessage();
          //$response['counterFailure'] ++;
        }

      }

    }

    if(!empty($messages)) {
      $html = '';
      foreach($messages as $message) {
        $html .= '<div>' . $message . '</div>';
      }
      echo $html;
    }
  }

}
