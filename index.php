<?php



class ms{

    private $post_list = array();
    private $get_list = array();

    public function get($path,$callback){
        $get_list[$path] = $callback;
    }

    public function post($path,$callback){
        $post_list[$path] = $callback;
    }

    public function run($port){

        $sunucu = stream_socket_server("tcp://0.0.0.0:$port", $errno, $errorMessage);

        while (1) 
        {
            $msg = "";
            $hata =0;
        
            print("Listening.... \n");
            $istemci = stream_socket_accept($sunucu);
        
            if(!$istemci) continue;
        
            print "Metadata alınıyor...\n";
            $meta = getir_metadata($istemci);
        
            if($meta["Method"] == "GET"){
                $get_list[$meta["page"]]();
            }

            if($meta["Method"] == "POST"){
                $post_list[$meta["page"]]();
            }

            print_r($meta);
        
            print "İçerik alınıyor...\n";
            $icerik = getir_icerik($istemci,$meta);
        
            if($icerik){
                print "Veriler parçalanıyor...\n";
                $liste = parcala_icerik($icerik);
                print_r($liste);
            }else{
                print "İçerik bulunamadı...\n";
                $hata = 1;
            }
        
        
            if($hata != 1 && $liste){
                print "Json oluşturuluyor...\n";
                $json = olustur_json($liste["dealer_code"],$liste["work_order_id"],$liste["VIN"],
                $liste["image_file_id"],$liste["image_file_base64"],$liste["max_return_image_file"]);
        
                print "Json apiye gönderiliyor...\n";
                $cevap = gonder_api($json);
                print_r($cevap);
            }
        
            $msg .= json_encode($cevap);
        
            print("Sayfa oluşturuluyor.... \n");
            $Sdata = olustur_sayfa($msg);
            print("Sayfa gönderiliyor.... \n");
            stream_socket_sendto($istemci,$Sdata);
        
            print "Bağlantı sonlandırılıyor...\n";
            fclose($istemci);
        }
        
        fclose($server);

    }

};



function parcala_icerik($content){
    $liste = array();
    $data = explode("&",$content);

    foreach($data as $part){
        $p = explode("=",$part);
        $liste[$p[0]] = $p[1];      
    }
    return $liste;
}

function getir_metadata($client){

    $Rdata = stream_get_line($client,9999999,"\r\n\r\n");
    $array = explode("\r\n",$Rdata);

    $head = explode(" ",$array[0]);
    $liste["Method"]=$head[0];
    $liste["Page"] = $head[1];
    $liste["Http"] =$head[2];

    array_splice($array,0,1);

    foreach($array as $part){
        $p = explode(":",$part);
        $liste[$p[0]] = $p[1];      
    }

    return $liste;
}


function getir_icerik($client,$meta){
    $len = $meta["Content-Length"];
    if($len == 0)
        return NULL;

    return stream_get_line($client,$len);
}



function olustur_sayfa($extra){

    ob_start();
    form();
    print $extra;
    $output = ob_get_contents();
    ob_end_clean();
    $header = "HTTP/1.0 200 OK\r\n";
    $header .= "Content-Type: text/html\r\n\r\n";
    $data = $header . $output;
    return $data;

}


function olustur_json($dealer_code,$work_order_id,$vin,$image_file_id,$image_file_base64,$max_return_image_file){

    $json = "{
        \"request\": {
            \"dealer_code\": \"$dealer_code\",
            \"work_order_id\": \"$work_order_id\",
            \"VIN\": \"$vin\",
            \"image_file_id\": \"$image_file_id\",
            \"image_file_base64\": \"$image_file_base64\",
            \"max_return_image_file\": \"$max_return_image_file\"
        }}";

        return $json;
}

function gonder_api($json){

    //$url = "10.20.5.111:1041/json";

    $url = "https://data.messari.io/api/v1/assets/btc/metrics";

    $opts = array(
        'http'=>array(
          'method'=>"GET"
        )
      );
      
      $context = stream_context_create($opts);
      
      /* Yukarıdaki başlıklarla www.example.com'a bir HTTP isteği gönderelim */
      $fp = fopen($url, 'r', false, $context);
      $ret = fread($fp,9999999);
      fclose($fp);

    return json_decode($ret);
}

function form(){ ?>
<script>

function getBase64(file) {
   var reader = new FileReader();
   reader.readAsDataURL(file);
   reader.onload = function () {
    document.getElementById('image_file_base64').innerHTML = reader.result;
    console.log(reader.result);
   };
   reader.onerror = function (error) {
     console.log('Error: ', error);
   };
}

function fileChanged(event) {
  var target = event.target || event.srcElement;
  console.log(target.files);
  var file = document.getElementById('image').files[0];
  getBase64(file);

}

</script>
<meta charset="UTF-8">
<center>
<table border=0>
<form action="?" method=post >
<tr> 
<td>Dealer Code :</td><td> <input type=text name=dealer_code id=dealer_code></td>
</tr>
<tr>
<td>Order ID :</td><td> <input type=text name=work_order_id id=work_order_id></td>
</tr>
<tr>
<td>VIN: </td><td><input type=text name=VIN id=vin></td>
</tr>
<tr>
<td>Image ID :</td><td> <input type=text name=image_file_id id=image_file_id></td>
</tr>
<tr>
<td>Max Image : </td><td><input type=text name=max_return_image_file id=max_return_image_file></td>
</tr>
<tr>
<td>Image : </td><td><input type='file' name='image' id='image' onchange='fileChanged(event)'></td>
</tr>
<tr>
<td>Base64 : </td><td> <textarea name=image_file_base64 id=image_file_base64 rows="6" cols="100" readonly></textarea></td>
</tr>
<tr>
<td> </td><td><input type="submit" value="Sorgula" name="submit" ></td>
</tr>
</form>
</table>
<?php }?>