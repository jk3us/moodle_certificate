<?php
//HTML2PDF by ClÈment Lavoillotte
//ac.lavoillotte@noos.fr
//webmaster@streetpc.tk
//http://www.streetpc.tk
// Rev:
//    Remote Learner Custom Edit By John T. Macklin 8/13/2008 11:33:58 PM
//    +180 Added Customized function function Output($name='',$dest='') to
//    include the correct customized headers for MSIE Browser Type when ($dest='I')

//define('FPDF_FONTPATH','font/');
require($CFG->libdir.'/fpdf/fpdf.php');

//function hex2dec
//returns an associative array (keys: R,G,B) from
//a hex html code (e.g. #3FE5AA)
function hex2dec($couleur = "#000000"){
    $R = substr($couleur, 1, 2);
    $rouge = hexdec($R);
    $V = substr($couleur, 3, 2);
    $vert = hexdec($V);
    $B = substr($couleur, 5, 2);
    $bleu = hexdec($B);
    $tbl_couleur = array();
    $tbl_couleur['R']=$rouge;
    $tbl_couleur['G']=$vert;
    $tbl_couleur['B']=$bleu;
    return $tbl_couleur;
}

//conversion pixel -> millimeter in 72 dpi
function px2mm($px){
    return $px*25.4/72;
}

function txtentities($html){
    $trans = get_html_translation_table(HTML_ENTITIES);
    $trans = array_flip($trans);
    return strtr($html, $trans);
}
////////////////////////////////////

class PDF extends FPDF_Protection
{
//variables of html parser
var $B;
var $I;
var $U;
var $HREF;
var $fontList;
var $issetfont;
var $issetcolor;

function PDF($orientation='P',$unit='mm',$format='A4')
{
    //Call parent constructor
    $this->FPDF_Protection($orientation,$unit,$format);
    //Initialization
    $this->B=0;
    $this->I=0;
    $this->U=0;
    $this->HREF='';
    $this->fontlist=array("arial","times","courier","helvetica","symbol");
    $this->issetfont=false;
    $this->issetcolor=false;
}

//////////////////////////////////////
//html parser

function WriteHTML($html)
{
    $html=strip_tags($html,"<b><u><i><a><img><p><br><strong><em><font><tr><blockquote>"); //remove all unsupported tags
    $html=str_replace("\n",' ',$html); //replace carriage returns by spaces
    $a=preg_split('/<(.*)>/U',$html,-1,PREG_SPLIT_DELIM_CAPTURE); //explodes the string
    foreach($a as $i=>$e)
    {
        if($i%2==0)
        {
            //Text
            if($this->HREF)
                $this->PutLink($this->HREF,$e);
            else
                $this->Write(5,stripslashes(txtentities($e)));
        }
        else
        {
            //Tag
            if($e{0}=='/')
                $this->CloseTag(strtoupper(substr($e,1)));
            else
            {
                //Extract attributes
                $a2=explode(' ',$e);
                $tag=strtoupper(array_shift($a2));
                $attr=array();
                foreach($a2 as $v)
                    if(ereg('^([^=]*)=["\']?([^"\']*)["\']?$',$v,$a3))
                        $attr[strtoupper($a3[1])]=$a3[2];
                $this->OpenTag($tag,$attr);
            }
        }
    }
}

function OpenTag($tag,$attr)
{
    //Opening tag
    switch($tag){
        case 'STRONG':
            $this->SetStyle('B',true);
            break;
        case 'EM':
            $this->SetStyle('I',true);
            break;
        case 'B':
        case 'I':
        case 'U':
            $this->SetStyle($tag,true);
            break;
        case 'A':
            $this->HREF=$attr['HREF'];
            break;
        case 'IMG':
            if(isset($attr['SRC']) and (isset($attr['WIDTH']) or isset($attr['HEIGHT']))) {
                if(!isset($attr['WIDTH']))
                    $attr['WIDTH'] = 0;
                if(!isset($attr['HEIGHT']))
                    $attr['HEIGHT'] = 0;
                $this->Image($attr['SRC'], $this->GetX(), $this->GetY(), px2mm($attr['WIDTH']), px2mm($attr['HEIGHT']));
            }
            break;
        case 'TR':
        case 'BLOCKQUOTE':
        case 'BR':
            $this->Ln(10);
            break;
        case 'P':
            $this->Ln(20);
            break;
        case 'FONT':
            if (isset($attr['COLOR']) and $attr['COLOR']!='') {
                $coul=hex2dec($attr['COLOR']);
                $this->SetTextColor($coul['R'],$coul['G'],$coul['B']);
                $this->issetcolor=true;
            }
            if (isset($attr['FACE']) and in_array(strtolower($attr['FACE']), $this->fontlist)) {
                $this->SetFont(strtolower($attr['FACE']));
                $this->issetfont=true;
            }
            break;
    }
}

function CloseTag($tag)
{
    //Closing tag
    if($tag=='STRONG')
        $tag='B';

    if($tag=='/span')
        $tag='B';

    if($tag=='EM')
        $tag='I';
    if($tag=='B' or $tag=='I' or $tag=='U')
        $this->SetStyle($tag,false);
    if($tag=='A')
        $this->HREF='';
    if($tag=='FONT'){
        if ($this->issetcolor==true) {
            $this->SetTextColor(0);
        }
        if ($this->issetfont) {
            $this->SetFont('arial');
            $this->issetfont=false;
        }
    }
}

function Output($name='',$dest='')
{
     //Output PDF to some destination
    //Finish document if necessary
    if($this->state<3)
        $this->Close();
    //Normalize parameters
    if(is_bool($dest))
        $dest=$dest ? 'D' : 'F';
    $dest=strtoupper($dest);
    if($dest=='')
    {
        if($name=='')
        {
            $name='doc.pdf';
            $dest='I';
        }
        else
            $dest='F';
    }
    switch($dest)
    {
        case 'I':
            //Send to standard output
            if(ob_get_contents())
                $this->Error('Some data has already been output, can\'t send PDF file');

           if(php_sapi_name()!='cli')
            {
              if(headers_sent())
                   $this->Error('Some data has already been output to browser, can\'t send PDF file');

                //We send to a browser diffrently using IE than FireFox due to Mime Types
                if(isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'],'MSIE')){
                  //mtrace("MSIE <br />"); //Remote Learner Rev's begin by John T. Macklin (C) 2008
                   header('Expires: 0');
                   header('Cache-Control: private, pre-check=0, post-check=0, max-age=0, must-revalidate');
                   header('Connection: Keep-Alive');
                   header('Content-Language: ' . current_language());
                   header('Keep-Alive: timeout=5, max=100');
                   header('Content-Type: application/pdf');
                   header('Content-Length: '.strlen($this->buffer));
                   header('Content-Disposition: inline; filename="'.$name.'"');
                   header('Content-Transfer-Encoding: binary');
                   header('Pragma: no-cache');
                   header('Pragma: expires');
                   header('Expires: Mon, 20 Aug 1969 09:23:00 GMT');
                   header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
                   echo $this->buffer;

                }else{  // Must not Be MSIE
                   header('Content-Type: application/pdf');
                   header('Content-Length: '.strlen($this->buffer));
                   header('Content-Disposition: inline; filename="'.$name.'"');
                   echo $this->buffer;
                }


            }

            break;
        case 'D':
            //Download file
            if(ob_get_contents())
                $this->Error('Some data has already been output, can\'t send PDF file');

            if(isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'],'MSIE'))
                header('Content-Type: application/force-download');
            else
                header('Content-Type: application/octet-stream');

               if(headers_sent())
                  $this->Error('Some data has already been output to browser, can\'t send PDF file');

                  header('Content-Length: '.strlen($this->buffer));
                  header('Content-disposition: attachment; filename="'.$name.'"');

            echo $this->buffer;
            break;
        case 'F':
            //Save to local file
            $f=fopen($name,'wb');
            if(!$f)
                $this->Error('Unable to create output file: '.$name);
            fwrite($f,$this->buffer,strlen($this->buffer));
            fclose($f);
            break;
        case 'S':
            //Return as a string
            return $this->buffer;
        default:
            $this->Error('Incorrect output destination: '.$dest);
    }
    return 0;
}


function SetStyle($tag,$enable)
{
    //Modify style and select corresponding font
    $this->$tag+=($enable ? 1 : -1);
    $style='';
    foreach(array('B','I','U') as $s)
        if($this->$s>0)
            $style.=$s;
    $this->SetFont('',$style);
}

function PutLink($URL,$txt)
{
    //Put a hyperlink
    $this->SetTextColor(0,0,255);
    $this->SetStyle('U',true);
    $this->Write(5,$txt,$URL);
    $this->SetStyle('U',false);
    $this->SetTextColor(0);
}

}//end of class
?>