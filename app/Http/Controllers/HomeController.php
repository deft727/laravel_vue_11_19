<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UploadFile;

class HomeController extends Controller
{
    public function __construct()
    {
    }

    public function index() {
        return view('index');
    }

    public function translation(Request $request) {
        $uFileId = $request->uFileId;
        $uploadFile = UploadFile::find($uFileId);
        $fileName = $uploadFile->file_name;
        $arr = explode('.', $fileName);
        $fName = $arr[0];
        
        $htmlFilename = public_path() . '/uploads/' . $uFileId . '/html/' . $fName . '_translated.html';

        $content = file_get_contents($htmlFilename);
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($content);
        libxml_clear_errors();
        // $head = $dom->getElementsByTagName("head")->item(0);

        // $style = $dom->createElement('style');
        // $style->nodeValue = ".skiptranslate, #google_translate_element {display: none;} body {min-height: 0px !important; position: static !important; top: 0px !important;}";
        // $head->appendChild($style);

        // $body = $dom->getElementsByTagName("body")->item(0);
        // $div1 = $dom->createElement('div');
        // $div1->setAttribute("id", "google_translate_element");
        // $body->appendChild($div1);

        // // $div2 = $dom->createElement("div", "English");
        // // $div2->setAttribute("id", "translationDetector");
        // // $div2->setAttribute("style", "visibility:hidden");
        // // $body->appendChild($div2);

        // $script1 = $dom->createElement('script');
        // $script1->setAttribute("src", "https://code.jquery.com/jquery-3.2.1.slim.min.js");
        // $script1->setAttribute("crossorigin", "anonymous");
        // $body->appendChild($script1);

        // $script2 = $dom->createElement('script');
        // $script2->setAttribute("src", "https://cdnjs.cloudflare.com/ajax/libs/jquery-cookie/1.4.1/jquery.cookie.min.js");
        // $script2->setAttribute("crossorigin", "anonymous");
        // $body->appendChild($script2);

        // $script3 = $dom->createElement('script');
        // $script3->setAttribute("src", "https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit");
        // $body->appendChild($script3);

        // $script4 = $dom->createElement('script');
        // $script4->nodeValue = "
        //     $.cookie('googtrans', '/en');
        //     function googleTranslateElementInit() {
        //         $.when(
        //             new google.translate.TranslateElement({pageLanguage: '" . $uploadFile->from_lang . "', includedLanguages: '" . $uploadFile->to_lang . "',
        //                 layout: google.translate.TranslateElement.FloatPosition.TOP_LEFT}, 'google_translate_element')
        //         ).done(function(){
        //             setTimeout(function() {
        //                 var select = document.getElementsByClassName('goog-te-combo')[0];
        //                 select.selectedIndex = 1;
        //                 select.addEventListener('click', function () {
        //                     select.dispatchEvent(new Event('change'));
        //                 });
        //                 select.click();
        //             }, 2000);
        //         });
        //     }";
        // $body->appendChild($script4);

        // $script5 = $dom->createElement('script');
        // $script5->nodeValue = "document.getElementById('translationDetector').addEventListener('DOMSubtreeModified', translationCallback, false);";
        // $body->appendChild($script5);

        return($dom->saveHTML());
    }
    
}
