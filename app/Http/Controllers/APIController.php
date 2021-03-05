<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PDF;
use App\Models\UploadFile;

class APIController extends Controller
{
    public function __construct()
    {
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
                'message' => 'File uploaded successfully to server',
                'uFileId' => $uploadFile->id
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function convertFile(Request $request) {
        try {
            $uFileId = $request->uFileId;
            $uploadFile = UploadFile::find($uFileId);

            if (!$uploadFile) {
                return response()->json([
                    'message' => 'File not exist on server'
                ]);
            }
            
            $endpoint = "https://sandbox.zamzar.com/v1/jobs";
            $apiKey = env('ZAMZAR_API_KEY');
            $sourceFilePath = public_path() . '/uploads/' . $uploadFile->id . '/original/' . $uploadFile->file_name;
            $targetFormat = "html5-1page";

            // Since PHP 5.5+ CURLFile is the preferred method for uploading files
            if(function_exists('curl_file_create')) {
                $sourceFile = curl_file_create($sourceFilePath);
            } else {
                $sourceFile = '@' . realpath($sourceFilePath);
            }

            $postData = array(
                "source_file" => $sourceFile,
                "target_format" => $targetFormat
            );

            $ch = curl_init(); // Init curl
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
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        } 
    }

    public function checkJob(Request $request) {
        try {
            $uFileId = $request->uFileId;
            $jobId = $request->jobId;
          
            $endpoint = "https://sandbox.zamzar.com/v1/jobs/$jobId";
            $apiKey = env('ZAMZAR_API_KEY');

            $ch = curl_init(); // Init curl
            curl_setopt($ch, CURLOPT_URL, $endpoint); // API endpoint
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return response as a string
            curl_setopt($ch, CURLOPT_USERPWD, $apiKey . ":"); // Set the API key as the basic auth username
            $body = curl_exec($ch);
            curl_close($ch);

            $job = json_decode($body, true);

            if ($job['status'] == 'successful') {
                $uploadFile = UploadFile::find($uFileId);

                if ($uploadFile) {
                    $uploadFile->target_files = json_encode($job['target_files']);
                    $uploadFile->status = 'successful';
                    $uploadFile->save();
                }

                return response()->json([
                    'message' => 'Zamzar job finished successfully',
                    'status' => 'successful',
                    'targetFiles' => $job['target_files']
                ]);
            } else {
                return response()->json([
                    'message' => 'Zamzar job still doing...',
                    'status' => 'initialising'
                ]);
            }
        } catch (\Throwable $th) {
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

            $endpoint = "https://sandbox.zamzar.com/v1/files/$targetFileId/content";
            $apiKey = env('ZAMZAR_API_KEY');

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
                'message' => 'Downloaded zamzar file ' . $targetFileName . ' to server successfully',
                'htmlFilename' => $localFilename
            ]);
        } catch (\Throwable $th) {
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
    //     } catch (\Throwable $th) {
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
    //     } catch (\Throwable $th) {
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
    //     } catch (\Throwable $th) {
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
    //     } catch (\Throwable $th) {
    //         return response()->json([
    //             'message' => $th->getMessage()
    //         ], 500);
    //     }
    // }
    
}