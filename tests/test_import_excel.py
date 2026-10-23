import datetime as dt
import pathlib
import sys
import tempfile
import unittest

from openpyxl import Workbook
sys.path.insert(0, str(pathlib.Path(__file__).resolve().parents[1]))
from import_excel import parse_excel, clean_cpf


class ImportacaoTest(unittest.TestCase):
    def planilha(self, linhas, headers=None):
        with tempfile.TemporaryDirectory() as pasta:
            wb = Workbook(); ws = wb.active
            ws.append(headers or ['Processo', 'Documento', 'Nome Perito', 'Data perícia', 'Nome Parte'])
            for linha in linhas: ws.append(linha)
            arquivo = pathlib.Path(pasta) / 'teste.xlsx'; wb.save(arquivo)
            return parse_excel(arquivo)

    def test_datas_distintas(self):
        r = self.planilha([['TESTE', '', 'Perito Teste', dt.date(2026, 11, 1), 'Pessoa'], ['TESTE', '', 'Perito Teste', dt.date(2026, 11, 2), 'Pessoa']])
        self.assertEqual(2, len(r))

    def test_duplicata_identica(self):
        linha = ['TESTE', '', 'Perito Teste', '01/11/2026', 'Pessoa']
        self.assertEqual(1, len(self.planilha([linha, linha])))

    def test_data_impossivel(self):
        self.assertIn('error', self.planilha([['TESTE', '', 'Perito', '31/02/2026', 'Pessoa']])[0])

    def test_formula(self):
        self.assertIn('error', self.planilha([['TESTE', '', 'Perito', '01/11/2026', '=1+1']])[0])

    def test_cabecalho_incompleto(self):
        self.assertIn('Cabeçalho', self.planilha([['TESTE']], ['Processo'])[0]['error'])

    def test_cpf_invalido(self):
        for cpf in ['1234567890', '11111111111', '12345678901']:
            with self.assertRaises(ValueError): clean_cpf(cpf)

    def test_cpf_com_e_sem_mascara(self):
        self.assertEqual(clean_cpf('52998224725'), clean_cpf('529.982.247-25'))
        self.assertEqual('012.345.678-90', clean_cpf('01234567890'))

    def test_mascaras_nao_duplicam_agendamento(self):
        registros = self.planilha([
            ['00000010020264058000', '52998224725', 'Perito Teste', '01/11/2030', 'Pessoa'],
            ['0000001-00.2026.4.05.8000', '529.982.247-25', 'Perito Teste', '01/11/2030', 'Pessoa'],
        ])
        self.assertEqual(1, len(registros))
        self.assertNotIn('error', registros[0])
        self.assertEqual('529.982.247-25', registros[0]['cpf'])

    def test_cpf_numerico_sem_zero_e_data_excel(self):
        registros = self.planilha([['LEGADO-123', 52998224725, 'Perito Teste', dt.date(2030, 11, 1), 'Pessoa']])
        self.assertEqual('529.982.247-25', registros[0]['cpf'])
        self.assertEqual('2030-11-01', registros[0]['data_pericia'])
        self.assertEqual('LEGADO-123', registros[0]['processo'])

    def test_conflito(self):
        self.assertIn('error', self.planilha([['TESTE', '', 'Perito', '01/11/2026', 'Pessoa A'], ['TESTE', '', 'Perito', '01/11/2026', 'Pessoa B']])[0])

    def test_sem_data_nao_presume_hoje(self):
        self.assertIn('error', self.planilha([['TESTE', '', 'Perito', '', 'Pessoa']])[0])


if __name__ == '__main__': unittest.main()
