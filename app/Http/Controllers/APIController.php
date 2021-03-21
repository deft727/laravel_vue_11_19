<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PDF;
use App\Models\UploadFile;

class APIController extends Controller
{
    public $_accessToken = '';
    public $_tokenType = '';
    public $workflows = [
        'CONVERTPDFTOWORD' => '0000000000000001',
        'CONVERTPDFTOEXCEL' => '0000000000000002',
        'CONVERTFILESTOPDF' => '0000000000000003',
        'COMBINEFILESTOPDF' => '0000000000000004',
        'TOHTML' => '0000000006AE5CBE',
        'TOPDF' => '0000000006AE5CC8'
    ];
    public $file_types = ['doc', 'docx', ];
    
    public function __construct()
    {
        // $this->getToken();
    }

    public function getToken() {
        try {
            $token_header = [
                'Content-Type: application/x-www-form-urlencoded'
            ];
            $token_url = 'https://www.easypdfcloud.com/oauth2/token';
            $method = 'POST';
            $params = array(
                'grant_type' => env('PDFCLOUD_GRANT_TYPE'),
                'client_id' => env('PDFCLOUD_CLIENT_ID'),
                'client_secret' => env('PDFCLOUD_CLIENT_SECRET'),
                'scope' => env('PDFCLOUD_SCOPE')
            );
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $token_url); // API endpoint
            curl_setopt($ch, CURLOPT_HTTPHEADER, $token_header);    
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            // curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params, '', '&'));
            // curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false); // Enable the @ prefix for uploading files
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return response as a string
            $body = curl_exec($ch);
            curl_close($ch);
    
            $response = json_decode($body, true);
            $this->_accessToken = $response['access_token'];
            $this->_tokenType = $response['token_type'];
            
            return response()->json([
                'message' => 'Got Token successfully from server'
            ], 200);
        } catch (Exception $th) {
            throw new Exception($th->getMessage());
        }
    }
    public function checkToken() {
        try {
            echo $this->_accessToken;
            $token_header = [
                'Authorization: Bearer ' . $this->_accessToken
            ];
            $token_url = 'https://api.easypdfcloud.com/v1/workflows';
            $method = 'GET';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $token_url); // API endpoint
            curl_setopt($ch, CURLOPT_HTTPHEADER, $token_header);    
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            // curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false); // Enable the @ prefix for uploading files
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return response as a string
            $body = curl_exec($ch);
            curl_close($ch);
    
            $response = json_decode($body, true);
            return true;
        } catch (Exception $th) {
            // echo $th->getMessage();
            // print_r($th);
            throw new Exception($th->getMessage());
        }
    }

    public function callService($url, $params = [], $header = [], $method = 'POST') {
        try {
            if($this->_accessToken == '') {
                // get token api
                $this->getToken();
            }
            $ch = curl_init(); // Init curl
            // check token
            $this->checkToken();
            $token_header = [
                'Authorization: Bearer ' . $this->_accessToken
            ];
            curl_setopt($ch, CURLOPT_URL, $url); // API endpoint
            $header = array_merge($token_header, $header);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if($method != 'GET') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params, '', '&'));
            }
            // curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false); // Enable the @ prefix for uploading files
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return response as a string
            // curl_setopt($ch, CURLOPT_USERPWD, $apiKey . ":"); // Set the API key as the basic auth username
            $body = curl_exec($ch);
            curl_close($ch);
    
            $response = json_decode($body, true);
            return $response;
        } catch (Exception $th) {
            $error = $th->getMessage();
            if($requireToken && strpos($error, 'WWW-Authenticate: Bearer') !== false) {
                $this->getToken();
            }else{
                throw new Exception($error);
            }
        }
    }

    public function uploadFile(Request $request) {
        try {
            $file = $request->file;
            $fileType = $request->fileType;
            $fileName = $request->fileName;
            $fileSize = $request->fileSize;
            $fromLang = $request->fromLang;
            $toLang = $request->toLang;
            $uploadFile = UploadFile::create([
                'file_type' => $fileType,
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'from_lang' => $fromLang,
                'to_lang' => $toLang
            ]);

            Storage::disk('uploads')->put('/' . $uploadFile->id . '/original/' . $fileName, file_get_contents($file));

            return response()->json([
                'uFileId' => $uploadFile->id
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function convertEasyPdfCloud($workflowId, $inputFileName, $outputFileName) {
        try {
            $client = new \Bcl\EasyPdfCloud\Client(env('PDFCLOUD_CLIENT_ID'), env('PDFCLOUD_CLIENT_SECRET'));

            $enableTestMode = true;

            $job = $client->startNewJobWithFilePath($workflowId, $inputFileName, $enableTestMode);
            // Wait until job execution is completed
            $result = $job->waitForJobExecutionCompletion();
            // Save output to file
            $outputDirname = dirname($outputFileName);
            if (!is_dir($outputDirname)) {
                mkdir($outputDirname, 0755, true);
            }
            $fh = fopen($outputFileName, "wb");
            file_put_contents($outputFileName, $result->getFileData()->getContents());
            fclose($fh);
            
            return true;
        } catch (\Throwable $th) {
            return $th->getMessage();
        }
    }

    public function convertFile(Request $request) {
        try {
            $uFileId = $request->uFileId;
            $uploadFile = UploadFile::find($uFileId);

            if (!$uploadFile) {
                return response()->json([
                    'message' => 'File not exist on server'
                ], 500);
            }

            $fileName = $uploadFile->file_name;
            $arr = explode('.', $fileName);
            $fName = $arr[0];

            $inputFileName = public_path() . '/uploads/' . $uploadFile->id . '/original/' . $uploadFile->file_name;

            if ($uploadFile->file_type !== 'application/pdf') {
                $workflowId = $this->workflows['CONVERTFILESTOPDF'];
                
                $outputFileName = public_path() . '/uploads/' . $uploadFile->id . '/original/' . $fName . '.pdf';

                $b = $this->convertEasyPdfCloud($workflowId, $inputFileName, $outputFileName);

                if ($b === true) {
                    $inputFileName = $outputFileName;
                } else {
                    return response()->json([
                        'message' => $th->getMessage()
                    ], 500);
                }
            }

            // $ext = path1info($uploadFile->file_name, PATHINFO_EXTENSION);
            $workflowId = $this->workflows['TOHTML'];
            // if(in_array($ext, $this->file_types)) {
            // }
            // $endpoint = "https://api.easypdfcloud.com/v1/workflows/$job_type/jobs";
            
            $outputFileName = public_path() . '/uploads/' . $uploadFile->id . '/html/' . $fName . '.html';

            $b = $this->convertEasyPdfCloud($workflowId, $inputFileName, $outputFileName);
            
            if ($b === true) {
                return response()->json([], 200);   
            } else {
                return response()->json([
                    'message' => $th->getMessage()
                ], 500);
            }
            
            // Since PHP 5.5+ CURLFile is the preferred method for uploading files
            // if(function_exists('curl_file_create')) {
            //     $sourceFile = curl_file_create($sourceFilePath);
            // } else {
            //     $sourceFile = '@' . realpath($sourceFilePath);
            // }
            
            // $header = ['Content-Type: multipart/form-data'];
            // $postData = array(
            //     "file" => $sourceFile,
            //     "start" => true,
            //     "test" => true
            // );
            
            // $response = $this->callService($endpoint, $postData, $header);
            // if(isset($response['jobID'])) {
            //     $uploadFile->job_id = $response['jobID'];
            //     $uploadFile->status = 'initialising';
            //     $uploadFile->save();
            //     return response()->json([
            //         'message' => 'File converting started', //  on easyPDFCloud
            //         'jobId' => $response['jobID']
            //     ]);
            // }else{
            //     $uploadFile->status = 'initialize failed';
            //     $uploadFile->save();
            //     return response()->json([
            //         'message' => 'File converting failed'
            //     ], 500);
            // }
/*            $ch = curl_init(); // Init curl
            curl_setopt($ch, CURLOPT_URL, $endpoint); // API endpoint
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            // curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false); // Enable the @ prefix for uploading files
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return response as a string
            curl_setopt($ch, CURLOPT_USERPWD, $apiKey . ":"); // Set the API key as the basic auth username
            $body = curl_exec($ch);
            curl_close($ch);

            $response = json_decode($body, true);

            if ($response && !isset($response['errors'])) {
                $uploadFile->job_id = $response['id'];
                $uploadFile->status = 'initialising';
                $uploadFile->save();

                return response()->json([
                    'message' => 'File converting started on ZamZar',
                    'jobId' => $response['id']
                ]);
            } else {
                return response()->json([
                    'message' => 'File uploading to Zamzar failed'
                ], 500);
            }
            */
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        } 
    }

    private function getNewHtmlFilename($filename) {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        return basename($filename, ".".$ext).'html';
    }

    public function checkJob(Request $request) {
        try {
            $uFileId = $request->uFileId;
            $jobId = $request->jobId;
          
            $endpoint = "https://api.easypdfcloud.com/v1/jobs/$jobId/event";
            $response = $this->callService($endpoint);
            if(isset($response['status'])) {
                if ($response['status'] == 'completed') {
                    $uploadFile = UploadFile::find($uFileId);
    
                    if ($uploadFile) {
                        $uploadFile->target_files = $this->getNewHtmlFilename($uploadFile->file_name);
                        $uploadFile->status = 'successful';
                        $uploadFile->save();
                    }
    
                    return response()->json([
                        'message' => 'EasyPDFCloud job finished successfully',
                        'status' => 'successful'
                    ]);
                } else {
                    return response()->json([
                        'message' => 'EasyPDFCloud job still doing ('.$response['progress'].'%)...',
                        'status' => 'initialising'
                    ]);
                }
            }else{
                return response()->json([
                    'message' => 'EasyPDFCloud job still doing...',
                    'status' => 'initialising'
                ]);
            }
        } catch (Exception $th) {
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function downloadFile(Request $request) {
        try {
            $uFileId = $request->uFileId;
            $targetFileId = $request->targetFileId;
            $targetFileName = $request->targetFileName;

            $localFilename = public_path() . '/uploads/' . $uFileId . '/html/' . $targetFileName;
            $localDirname = dirname($localFilename);

            if (!is_dir($localDirname)) {
                mkdir($localDirname, 0755, true);
            }

            $endpoint = "https://api.easypdfcloud.com/v1/jobs/0000000001335B49/output/".$uploadFile->file_name;

            $ch = curl_init(); // Init curl
            curl_setopt($ch, CURLOPT_URL, $endpoint); // API endpoint
            curl_setopt($ch, CURLOPT_USERPWD, $apiKey . ":"); // Set the API key as the basic auth username
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);

            $fh = fopen($localFilename, "wb");
            curl_setopt($ch, CURLOPT_FILE, $fh);

            $body = curl_exec($ch);
            curl_close($ch);

            fclose($fh);

            return response()->json([
                'message' => 'Downloaded EasyPDFCloud file ' . $targetFileName . ' to server successfully',
                'htmlFilename' => $localFilename
            ]);
        } catch (Exception $th) {
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }

    // public function splitHtmlFile(Request $request) {
    //     try {
    //         $uFileId = $request->uFileId;
    //         $uploadFile = UploadFile::find($uFileId);
    //         $fileName = $uploadFile->file_name;
    //         $arr = explode('.', $fileName);
    //         $fName = $arr[0];
    //         $htmlFilename = public_path() . '/uploads/' . $uFileId . '/html/' . $fName . '.html';

    //         $htmlContents = file_get_contents($htmlFilename);
    //         // preg_match_all('/<[^>]++>|[^<>\s]++/', $htmlContents, $matches);
    //         // $srcSplittedHtml = implode(" ", $matches[0]);
    //         $splitted = str_split($htmlContents, 4900);
    //         $srcSplittedHtml = implode(":::SPLITTER:::", $splitted);

    //         $uploadFile->src_splitted_html = $srcSplittedHtml;
    //         $uploadFile->save();

    //         return response()->json([
    //             'message' => 'Splitting html file succeeded',
    //             'htmlCnt' => count($splitted)
    //         ]);
    //     } catch (Exception $th) {
    //         return response()->json([
    //             'message' => $th->getMessage()
    //         ], 500);
    //     }
    // }

    // public function word_chunk($str, $len = 76, $end = ":::SPLITTER:::") {
    //     $pattern = '~.{1,' . $len . '}~u'; // like "~.{1,76}~u"
    //     $str = preg_replace($pattern, '$0' . $end, $str);
    //     return rtrim($str, $end);
    // }

    // public function translateHtml(Request $request) {
    //     try {
    //         $uFileId = $request->uFileId;
    //         $fIndex = $request->fIndex;
    //         $uploadFile = UploadFile::find($uFileId);
    //         $srcSplittedHtml = $uploadFile->src_splitted_html;
    //         $splitted = explode(":::SPLITTER:::", $srcSplittedHtml);

    //         $apiKey = 'AIzaSyCbifPGAIYvd1PsPw5csoNnJcx4Ebq0emM';
    //         $value = $splitted[$fIndex];
	
    //         $toLang = $uploadFile->to_lang;

    //         $url ="https://translation.googleapis.com/language/translate/v2?key=$apiKey&q=$value&target=$toLang&format=html";

    //         $ch = curl_init();
    //         curl_setopt($ch, CURLOPT_URL, $url);
    //         curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    //         curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    //         $body = curl_exec($ch);
    //         curl_close($ch);

    //         $json = json_decode($body);

    //         if ($json && $json->data) {
    //             $trnsSplittedHtml = $uploadFile->trns_splitted_html;
    //             $trnsSplittedHtml .= $json->data->translations[0]->translatedText;

    //             $uploadFile->trns_splitted_html = $trnsSplittedHtml;
    //             $uploadFile->save();
    //         }
    //         return response()->json([
    //             'message' => 'Translating html file succeeded'
    //         ]);
    //     } catch (Exception $th) {
    //         return response()->json([
    //             'message' => $th->getMessage()
    //         ], 500);
    //     }
    // }

    // public function mergeHtmls(Request $request) {
    //     try {
    //         $uFileId = $request->uFileId;
    //         $uploadFile = UploadFile::find($uFileId);
    //         $fileName = $uploadFile->file_name;
    //         $arr = explode('.', $fileName);
    //         $fName = $arr[0];
    //         $transHtmlFilename = public_path() . '/uploads/' . $uFileId . '/translated/' . $fName . '.html';
    //         $localDirname = dirname($transHtmlFilename);

    //         if (!is_dir($localDirname)) {
    //             mkdir($localDirname, 0755, true);
    //         }

    //         $trnsSplittedHtml = $uploadFile->trns_splitted_html;

    //         $fh = fopen($transHtmlFilename, "wb");
    //         file_put_contents($transHtmlFilename, $trnsSplittedHtml);
    //         fclose($fh);

    //         return response()->json([
    //             'message' => 'Merging htmls succeeded',
    //             'htmlFilename' => $transHtmlFilename
    //         ]);
    //     } catch (Exception $th) {
    //         return response()->json([
    //             'message' => $th->getMessage()
    //         ], 500);
    //     }
    // }

    // public function convertHtmlPdf(Request $request) {
    //     try {
    //         $uFileId = $request->uFileId;
    //         $uploadFile = UploadFile::find($uFileId);
    //         $fileName = $uploadFile->file_name;
    //         $arr = explode('.', $fileName);
    //         $fName = $arr[0];
    //         $htmlFilename = public_path() . '/uploads/' . $uFileId . '/html/' . $fName . '.html';
    //         $pdfFilename = public_path() . '/uploads/' . $uFileId . '/converted/' . $fName . '.pdf';
            
    //         return PDF::loadFile($htmlFilename)->save($pdfFilename)->stream('download.pdf');
    //     } catch (Exception $th) {
    //         return response()->json([
    //             'message' => $th->getMessage()
    //         ], 500);
    //     }
    // }
    
}