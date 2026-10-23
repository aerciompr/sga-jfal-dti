<?php
require_once dirname(__DIR__).'/youtube.php';
$casos=[
 ['https://www.youtube.com/watch?v=M7lc1UVf-VE','M7lc1UVf-VE',''],
 ['https://youtu.be/M7lc1UVf-VE?t=5','M7lc1UVf-VE',''],
 ['https://m.youtube.com/shorts/M7lc1UVf-VE','M7lc1UVf-VE',''],
 ['https://www.youtube.com/playlist?list=PLinstitucional123','','PLinstitucional123'],
 ['https://www.youtube.com/watch?v=M7lc1UVf-VE&list=PLinstitucional123','','PLinstitucional123'],
 ['https://youtu.be/M7lc1UVf-VE?list=PLinstitucional123','','PLinstitucional123'],
];
foreach($casos as [$url,$video,$lista]) {
 if(fonteYouTube($url)!==['video_id'=>$video,'playlist_id'=>$lista]) throw new RuntimeException('Falha ao interpretar '.$url);
}
foreach(['https://youtube.com.evil.test/watch?v=M7lc1UVf-VE','https://evil.test/youtube.com/watch?v=M7lc1UVf-VE','http://youtube.com/watch?v=M7lc1UVf-VE','https://youtube.com/playlist?list[]=PLinstitucional123','https://youtube.com/playlist?list=xx','https://youtube.com/watch?v[]=M7lc1UVf-VE'] as $url) {
 if(fonteYouTube($url)!==null) throw new RuntimeException('Endereço inválido aceito');
}
echo "12 verificações de vídeo/playlist passaram.\n";
