"""Execute após integration.php, contra servidor local com a mesma fixture."""
import http.cookiejar
import json
import io
import csv
import datetime
import os
import re
import unittest
import urllib.error
import urllib.parse
import urllib.request
from openpyxl import Workbook

BASE = os.environ.get('SGA_TEST_URL', 'http://127.0.0.1:8086')
if urllib.parse.urlsplit(BASE).hostname not in ('127.0.0.1', 'localhost'):
    raise RuntimeError('Testes HTTP só podem usar servidor local descartável')


class Cliente:
    def __init__(self):
        self.cookies = http.cookiejar.CookieJar()
        self.http = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cookies))

    def abrir(self, path, dados=None):
        req = urllib.request.Request(BASE + '/' + path, data=urllib.parse.urlencode(dados).encode() if dados is not None else None)
        try:
            r = self.http.open(req, timeout=15)
        except urllib.error.HTTPError as erro:
            r = erro
        return r.code, r.read().decode('utf-8-sig'), r.geturl(), r.headers

    def login(self, usuario, senha='Senha-local-teste-2026!'):
        _, html, _, _ = self.abrir('index.php')
        csrf = re.search(r'name="csrf" value="([a-f0-9]+)"', html)[1]
        return self.abrir('index.php', dict(username=usuario, password=senha, csrf=csrf))


class HttpTests(unittest.TestCase):
    def test_rotas_por_perfil(self):
        comuns = ['dashboard.php', 'ajuda.php', 'alterar_senha_obrigatorio.php', 'logout.php']
        restritas = {
            'recepcao.php': {'admin', 'supervisor', 'recepcao'},
            'atendente.php': {'admin', 'perito'},
            'gerenciar_pauta.php': {'admin', 'supervisor'},
            'salas.php': {'admin', 'supervisor'},
            'usuarios.php': {'admin'},
            'auditoria.php': {'admin', 'supervisor'},
        }
        for perfil, login, senha in [
            ('admin', 'admin-test', 'Senha-local-teste-2026!'),
            ('supervisor', 'supervisor-test', 'Senha-local-teste-2026!'),
            ('recepcao', 'recepcao-test', 'Senha-local-teste-2026!'),
            ('perito', 'perito-a', 'Nova-senha-teste-2026!'),
        ]:
            c = Cliente(); self.assertEqual(200, c.login(login, senha)[0])
            for rota in comuns + list(restritas):
                with self.subTest(perfil=perfil, rota=rota):
                    status, _, destino, _ = c.abrir(rota)
                    self.assertLess(status, 500)
                    if rota in comuns or perfil in restritas[rota]:
                        esperado = 'atendente.php' if perfil == 'perito' and rota == 'dashboard.php' else rota
                        self.assertEqual(esperado, urllib.parse.urlsplit(destino).path.rsplit('/', 1)[-1])
                    else:
                        self.assertNotEqual(rota, urllib.parse.urlsplit(destino).path.rsplit('/', 1)[-1])
            status, _, _, _ = c.abrir('preview_importacao.php')
            self.assertEqual(410 if perfil in ('admin', 'supervisor') else 403, status)
            status, _, _, _ = c.abrir('api_verificar_outro_dia.php?busca=Teste')
            self.assertEqual(403 if perfil == 'perito' else 200, status)
        for rota in comuns + list(restritas) + ['preview_importacao.php', 'api_verificar_outro_dia.php']:
            with self.subTest(anonimo=rota):
                status, _, destino, _ = Cliente().abrir(rota)
                self.assertEqual(200, status)
                self.assertTrue(urllib.parse.urlsplit(destino).path.endswith('/index.php'))

    def test_sem_csrf(self):
        self.assertEqual(403, Cliente().abrir('index.php', {'username': 'admin-test', 'password': 'invalida'})[0])

    def test_tv_consultas_abertas_na_intranet(self):
        for endpoint in ['api_senhas.php', 'api_senhas_recentes.php', 'painel.php']:
            self.assertEqual(200, Cliente().abrir(endpoint)[0])

    def test_paginas_administrativas(self):
        c = Cliente(); self.assertEqual(200, c.login('admin-test')[0])
        for pagina in ['dashboard.php','recepcao.php','gerenciar_pauta.php','atendente.php','usuarios.php','salas.php','auditoria.php','auditoria.php?aba=logs','ajuda.php']:
            status, html, url, headers = c.abrir(pagina)
            self.assertEqual(200, status, pagina + ': ' + html[:150])
            self.assertIn(pagina.split('?')[0], url)
            self.assertIn('no-store', headers.get('Cache-Control', ''))
            self.assertNotIn('cdn.tailwindcss.com', html)
            for form in re.findall(r'<form\b.*?</form>', html, re.S | re.I):
                if re.search(r'method=["\']post["\']', form, re.I):
                    self.assertIn('name="csrf"', form, pagina)

    def test_recepcao_nao_gerencia_usuarios(self):
        c=Cliente(); c.login('recepcao-test')
        self.assertIn('dashboard.php',c.abrir('usuarios.php')[2])

    def test_busca_recepcao_respeita_homologacao(self):
        path='api_verificar_outro_dia.php?'+urllib.parse.urlencode(dict(busca='Pessoa Importada',hoje=datetime.date.today().isoformat()))
        c=Cliente();c.login('recepcao-test')
        self.assertEqual([],json.loads(c.abrir(path)[1]))
        admin=Cliente();admin.login('admin-test')
        self.assertGreaterEqual(len(json.loads(admin.abrir(path)[1])),1)

    def test_data_sql_rejeitada(self):
        c=Cliente(); c.login('admin-test'); _,html,_,_=c.abrir('gerenciar_pauta.php')
        csrf=re.search(r'name="csrf" value="([a-f0-9]+)"',html)[1]
        self.assertEqual(422,c.abrir('gerenciar_pauta.php',dict(csrf=csrf,action='mover_pauta',nova_data="2026-10-26' OR 1=1 --"))[0])

    def test_filtros_auditoria_nao_injetam_atributos(self):
        c=Cliente();c.login('admin-test')
        payload='\" onmouseover=\"alert(1)'
        status,html,_,_=c.abrir('auditoria.php?'+urllib.parse.urlencode(dict(sala=payload,perito=payload,ordenacao=payload)))
        self.assertEqual(200,status)
        self.assertNotIn(' onmouseover="alert(1)',html)

    def test_tv_eventos_sobrevivem_conclusao(self):
        c=Cliente(); status,html,url,_=c.abrir('painel.php')
        self.assertEqual(200,status); self.assertNotIn('token=',url)
        status,body,_,_=c.abrir('api_senhas.php?apos=0')
        self.assertEqual(200,status); eventos=json.loads(body)['eventos']
        self.assertGreaterEqual(len(eventos),2)
        self.assertEqual(sorted(int(e['id']) for e in eventos),[int(e['id']) for e in eventos])
        self.assertEqual(200,c.abrir('api_senhas_recentes.php')[0])

    def test_tv_abre_sem_autorizacao(self):
        c=Cliente();status,html,url,headers=c.abrir('painel.php')
        self.assertEqual(200,status)
        self.assertNotIn('Código de autorização',html)
        self.assertNotIn('token=',url)
        self.assertIn('Testar som',html)
        self.assertEqual(0,len(c.cookies))
        self.assertEqual('strict-origin-when-cross-origin',headers.get('Referrer-Policy'))

    def test_exportacao_csv(self):
        c=Cliente();c.login('admin-test')
        for aba in ['logs','atendimentos']:
            status,body,_,headers=c.abrir('auditoria.php?aba='+aba+'&action=exportar')
            self.assertEqual(200,status);self.assertIn('text/csv',headers.get('Content-Type',''));self.assertIn('id,',body)
            if aba=='logs': self.assertGreater(len(list(csv.reader(io.StringIO(body)))),500)

    def test_health(self):
        self.assertEqual({'status':'ok'},json.loads(Cliente().abrir('health.php')[1]))

    def test_importacao_com_previa_e_confirmacao(self):
        c=Cliente(); c.login('admin-test'); _,html,_,_=c.abrir('gerenciar_pauta.php')
        csrf=re.search(r'name="csrf" value="([a-f0-9]+)"',html)[1]
        wb=Workbook();ws=wb.active
        ws.append(['Processo','Documento','Nome Perito','Data perícia','Nome Parte'])
        ws.append(['HTTP-IMPORT-TESTE','','Perito Teste A','2030-01-02','Pessoa HTTP Teste'])
        dados=io.BytesIO();wb.save(dados)
        boundary='sga-test-boundary'
        corpo=b''
        for key,value in {'csrf':csrf,'action':'importar_excel'}.items():
            corpo+=(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n').encode()
        corpo+=(f'--{boundary}\r\nContent-Disposition: form-data; name="arquivo_excel"; filename="teste.xlsx"\r\nContent-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet\r\n\r\n').encode()+dados.getvalue()+f'\r\n--{boundary}--\r\n'.encode()
        req=urllib.request.Request(BASE+'/gerenciar_pauta.php',data=corpo,headers={'Content-Type':f'multipart/form-data; boundary={boundary}'})
        with c.http.open(req,timeout=30) as resposta:
            html=resposta.read().decode();self.assertIn('preview_importacao.php',resposta.geturl());self.assertIn('Pessoa HTTP Teste',html)
        csrf=re.search(r'name="csrf" value="([a-f0-9]+)"',html)[1]
        status,html,url,_=c.abrir('preview_importacao.php',{'csrf':csrf})
        self.assertEqual(200,status);self.assertIn('gerenciar_pauta.php',url);self.assertIn('Importação concluída',html)

    def test_sessao_excluida_perde_acesso(self):
        admin=Cliente();admin.login('admin-test');_,html,_,_=admin.abrir('usuarios.php')
        csrf=re.search(r'name="csrf" value="([a-f0-9]+)"',html)[1]
        login='temporario-http-test'
        admin.abrir('usuarios.php',dict(csrf=csrf,action='cadastrar',username=login,nome='Temporário',role='recepcao',password='Credencial-temporaria-2026!'))
        usuario=Cliente();status,html,url,_=usuario.login(login,'Credencial-temporaria-2026!')
        self.assertIn('alterar_senha_obrigatorio.php',url)
        csrf_usuario=re.search(r'name="csrf" value="([a-f0-9]+)"',html)[1]
        usuario.abrir('alterar_senha_obrigatorio.php',dict(csrf=csrf_usuario,nova_senha='Credencial-pessoal-2026!',confirma_senha='Credencial-pessoal-2026!'))
        usuario.login(login,'Credencial-pessoal-2026!')
        self.assertIn('recepcao.php',usuario.abrir('recepcao.php')[2])
        _,html,_,_=admin.abrir('usuarios.php')
        # ID obtido exclusivamente do cadastro fictício criado neste teste.
        match=re.search(r'editUser\((\d+),\s*&quot;'+login,html)
        self.assertIsNotNone(match)
        admin.abrir('usuarios.php',dict(csrf=csrf,action='deletar',id=match[1]))
        self.assertIn('index.php',usuario.abrir('recepcao.php')[2])

    @unittest.skipUnless(os.environ.get('SGA_APACHE')=='1', 'Exige Apache para verificar .htaccess')
    def test_arquivos_privados(self):
        for path in ['config.php','banco.php','seguranca.php','youtube.php','tv_autorizacao.php','scripts/migrate.php','import_excel.py','docker-compose.yml','.env','package.json']:
            self.assertIn(Cliente().abrir(path)[0], (403,404), path)


if __name__ == '__main__': unittest.main()
