"""Importação sem alterações de identidade, fórmulas ou datas presumidas."""
import datetime as dt
import json
import re
import sys
import unicodedata
import zipfile

import openpyxl

MAX_ROWS = 10000
MAX_UNCOMPRESSED = 50 * 1024 * 1024


def normalizar(texto):
    return ''.join(c for c in unicodedata.normalize('NFKD', str(texto)) if not unicodedata.combining(c)).lower().strip()


def clean_cpf(valor):
    if valor is None or str(valor).strip() in ('', '-'):
        return ''
    if isinstance(valor, float) and valor.is_integer():
        valor = int(valor)
    texto = str(valor).strip()
    if '/' in texto:
        return ''  # Linha de pessoa jurídica, sem CPF.
    digitos = re.sub(r'\D', '', texto)
    if re.search(r'[^\d.\-\s]', texto) or len(digitos) != 11 or len(set(digitos)) == 1:
        raise ValueError('CPF inválido; preserve os zeros à esquerda como texto na planilha')
    for n in (9, 10):
        verificador = (sum(int(digitos[i]) * (n + 1 - i) for i in range(n)) * 10) % 11
        if int(digitos[n]) != (0 if verificador == 10 else verificador):
            raise ValueError('CPF com dígitos verificadores inválidos')
    return f'{digitos[:3]}.{digitos[3:6]}.{digitos[6:9]}-{digitos[9:]}'


def data_pericia(valor):
    if isinstance(valor, (dt.datetime, dt.date)):
        return valor.strftime('%Y-%m-%d')
    texto = str(valor or '').strip()
    for formato in ('%Y-%m-%d', '%d/%m/%Y', '%Y-%m-%d %H:%M:%S', '%d/%m/%Y %H:%M:%S'):
        try:
            return dt.datetime.strptime(texto, formato).strftime('%Y-%m-%d')
        except ValueError:
            pass
    raise ValueError('Data da perícia ausente ou inválida')


def parse_excel(caminho):
    try:
        with zipfile.ZipFile(caminho) as arquivo:
            if sum(info.file_size for info in arquivo.infolist()) > MAX_UNCOMPRESSED:
                raise ValueError('Planilha descompactada excede 50 MB')
        with open(caminho, 'rb') as arquivo:
            wb = openpyxl.load_workbook(arquivo, read_only=True, data_only=False)
            try:
                sheet = wb.active
                if sheet.max_column > 100 or sheet.max_row > MAX_ROWS + 1:
                    raise ValueError('Limite de 100 colunas e 10.000 linhas excedido')
                linhas = sheet.iter_rows(values_only=True)
                headers = [normalizar(v or '') for v in next(linhas)]
                def coluna(regra, obrigatoria=True):
                    indices = [i for i, h in enumerate(headers) if regra(h)]
                    if len(indices) != 1 and obrigatoria:
                        raise ValueError('Cabeçalho ausente ou ambíguo: confira processo, documento, nome do perito e data da perícia')
                    return indices[0] if len(indices) == 1 else None
                ip = coluna(lambda h: 'processo' in h)
                idoc = coluna(lambda h: ('documento' in h or h in ('cpf', 'cpf parte', 'cpf periciado')) and 'perito' not in h and 'tipo' not in h, False)
                if idoc is None:
                    idoc = coluna(lambda h: h in ('parte', 'documento da parte', 'documento parte'))
                imed = coluna(lambda h: 'perito' in h and 'nome' in h)
                icpf = coluna(lambda h: 'perito' in h and 'cpf' in h, False)
                idata = coluna(lambda h: 'data' in h and 'pericia' in h and not any(x in h for x in ('fim', 'ultima')))
                inome = coluna(lambda h: 'nome' in h and 'perito' not in h and 'especialidade' not in h, False)
                registros = {}
                for numero, row in enumerate(linhas, 2):
                    if not any(v is not None and str(v).strip() for v in row):
                        continue
                    try:
                        if any(isinstance(v, str) and v.lstrip().startswith('=') for v in row):
                            raise ValueError('Fórmulas não são aceitas; exporte os valores')
                        processo = str(row[ip] or '').strip()
                        perito = ' '.join(str(row[imed] or '').split())
                        if not processo or len(processo) > 50 or not perito or len(perito) > 100:
                            raise ValueError('Processo ou nome do perito ausente/inválido')
                        data = data_pericia(row[idata])
                        documento = str(row[idoc] or '')
                        pessoa_juridica = '/' in documento
                        cpf = '' if pessoa_juridica else clean_cpf(row[idoc])
                        nome = '' if pessoa_juridica or inome is None else ' '.join(str(row[inome] or '').split())
                        if len(nome) > 100:
                            raise ValueError('Nome do periciado excede 100 caracteres')
                        cpf_perito = clean_cpf(row[icpf]) if icpf is not None else ''
                        chave = (re.sub(r'\W', '', processo), data, normalizar(perito))
                        registro = dict(processo=processo, perito=perito, cpf_perito=cpf_perito, cpf=cpf, periciado=nome, data_pericia=data)
                        if chave in registros:
                            anterior = registros[chave]
                            for campo in ('cpf', 'periciado', 'cpf_perito'):
                                if anterior[campo] and registro[campo] and normalizar(anterior[campo]) != normalizar(registro[campo]):
                                    raise ValueError('Partes ou profissionais conflitantes para o mesmo agendamento; revise a pauta')
                                anterior[campo] = anterior[campo] or registro[campo]
                        else:
                            registros[chave] = registro
                    except (ValueError, IndexError) as erro:
                        raise ValueError(f'Linha {numero}: {erro}') from erro
                if not registros:
                    raise ValueError('Nenhum agendamento encontrado')
                return list(registros.values())
            finally:
                wb.close()
    except Exception as erro:
        return [{'error': str(erro)}]


if __name__ == '__main__':
    resultado = parse_excel(sys.argv[1]) if len(sys.argv) == 2 else [{'error': 'Arquivo obrigatório'}]
    print(json.dumps(resultado, ensure_ascii=True))
    sys.exit(1 if resultado and 'error' in resultado[0] else 0)
