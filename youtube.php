<?php
// Apenas interpreta endereços; nenhuma consulta externa ou alteração de banco.
function fonteYouTube(string $url): ?array {
    if (strlen($url)>500) return null;
    $partes=parse_url(trim($url));
    if (!$partes || ($partes['scheme']??'')!=='https' || isset($partes['user']) || isset($partes['pass']) || isset($partes['port'])) return null;
    $host=strtolower($partes['host']??'');
    if (!in_array($host,['youtube.com','www.youtube.com','m.youtube.com','youtu.be','www.youtu.be'],true)) return null;
    parse_str($partes['query']??'', $query);
    $path=$partes['path']??'';
    $lista=$query['list']??null;
    if (is_string($lista) && preg_match('/^[a-zA-Z0-9_-]{10,100}$/D',$lista) &&
        ($host==='youtu.be' || $host==='www.youtu.be' || in_array($path,['/playlist','/watch','/embed/videoseries'],true) || str_starts_with($path,'/embed/'))) {
        return ['video_id'=>'','playlist_id'=>$lista];
    }
    $id=null;
    if (in_array($host,['youtu.be','www.youtu.be'],true)) $id=trim($path,'/');
    elseif ($path==='/watch') $id=$query['v']??null;
    elseif (preg_match('~^/(?:embed|shorts|live)/([a-zA-Z0-9_-]{11})/?$~D',$path,$m)) $id=$m[1];
    return is_string($id) && preg_match('/^[a-zA-Z0-9_-]{11}$/D',$id) ? ['video_id'=>$id,'playlist_id'=>''] : null;
}
function fonteYouTubePadrao(string $url): array {
    return fonteYouTube($url) ?? ['video_id'=>'5qap5aO4i9A','playlist_id'=>''];
}
