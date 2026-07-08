import pypdf
import json
import sys
import re

def clean_name(name_str):
    if not name_str:
        return ""
    # Standardize spaces
    s = " ".join(name_str.split())
    
    replacements = {
        "ADALT O": "ADALTO",
        "ADEILD O": "ADEILDO",
        "ADEILT ON": "ADEILTON",
        "ADRIAN A": "ADRIANA",
        "ADRIAN O": "ADRIANO",
        "ALBERT O": "ALBERTO",
        "ALBUQ UERQU E": "ALBUQUERQUE",
        "ALMEID A": "ALMEIDA",
        "AMILSO N": "AMILSON",
        "ANDER LY": "ANDERLY",
        "ANDRE ZA": "ANDREZA",
        "ANERE S": "ANERES",
        "ANGEL O": "ANGELO",
        "ANTONI O": "ANTONIO",
        "ANTUN ES": "ANTUNES",
        "APARE CIDA": "APARECIDA",
        "ARACE LY": "ARACELY",
        "ARAUJ O": "ARAUJO",
        "ARTHU R": "ARTHUR",
        "AUGUS TO": "AUGUSTO",
        "BALBIN O": "BALBINO",
        "BARBO SA": "BARBOSA",
        "BARRO S": "BARROS",
        "BENEDI TA": "BENEDITA",
        "BENEDI TO": "BENEDITO",
        "BENEV AL": "BENEVAL",
        "BENILT ON": "BENILTON",
        "BETANI A": "BETANIA",
        "BEZER RA": "BEZERRA",
        "BORGE S": "BORGES",
        "CAETA NO": "CAETANO",
        "CANDID O": "CANDIDO",
        "CARDO SO": "CARDOSO",
        "CARLO S": "CARLOS",
        "CARME M": "CARMEM",
        "CASAD O": "CASADO",
        "CASEIR A": "CASEIRA",
        "CAVAL CANTE": "CAVALCANTE",
        "CHRYS TINA": "CHRYSTINA",
        "CICER A": "CICERA",
        "CLARIC E": "CLARICE",
        "CLAUDI A": "CLAUDIA",
        "CLEBS ON": "CLEBSON",
        "CONCEI CAO": "CONCEICAO",
        "CORCI NO": "CORCINO",
        "CORDEI RO": "CORDEIRO",
        "CORRE I": "CORREI",
        "COUTIN HO": "COUTINHO",
        "CRISTI NA": "CRISTINA",
        "DAMIA O": "DAMIAO",
        "DANIEL L": "DANIEL",
        "DANTA S": "DANTAS",
        "DANUBI A": "DANUBIA",
        "DEODA TO": "DEODATO",
        "DIONIZI O": "DIONIZIO",
        "DJALD O": "DJALDO",
        "DOMIN GOS": "DOMINGOS",
        "DONAT O": "DONATO",
        "DOUGL AS": "DOUGLAS",
        "EDUAR DO": "EDUARDO",
        "EDUARD O": "EDUARDO",
        "EDVAL DO": "EDVALDO",
        "EMIDI O": "EMIDIO",
        "EUGENI O": "EUGENIO",
        "EZEQUI EL": "EZEQUIEL",
        "FALCA O": "FALCAO",
        "FAUST O": "FAUSTO",
        "FERNA NDES": "FERNANDES",
        "FERNA NDO": "FERNANDO",
        "FERREI RA": "FERREIRA",
        "FIRMIN O": "FIRMINO",
        "FRAGO SO": "FRAGOSO",
        "GABRIE L": "GABRIEL",
        "GABRIE LLA": "GABRIELLA",
        "GALDIN O": "GALDINO",
        "GEORG E": "GEORGE",
        "GERAL DO": "GERALDO",
        "GERLIA NO": "GERLIANO",
        "GILVA N": "GILVAN",
        "GILVAN ECE": "GILVANECE",
        "GIRLEN E": "GIRLENE",
        "GRACA S": "GRACAS",
        "GUEDE S": "GUEDES",
        "HAMILT ON": "HAMILTON",
        "HELEN A": "HELENA",
        "HENRIQ UE": "HENRIQUE",
        "HERCIL IO": "HERCILIO",
        "HOLAN DA": "HOLANDA",
        "HONOR IO": "HONORIO",
        "HORACI O": "HORACIO",
        "HUMBE RTO": "HUMBERTO",
        "IDALIN O": "IDALINO",
        "IVANILD O": "IVANILDO",
        "IVONE TE": "IVONETE",
        "IVONET E": "IVONETE",
        "JAILTO N": "JAILTON",
        "JOAQUI M": "JOAQUIM",
        "JOELS ON": "JOELSON",
        "JOSEA NE": "JOSEANE",
        "JOSEF A": "JOSEFA",
        "JOSINE TE": "JOSINETE",
        "JOSIVA NIO": "JOSIVANIO",
        "JOZILE NE": "JOZILENE",
        "JULIAN A": "JULIANA",
        "JURAC Y": "JURACY",
        "JUSTIN O": "JUSTINO",
        "KERSE VANI": "KERSEVANI",
        "KETHY L": "KETHYL",
        "LEONI A": "LEONIA",
        "LINALD O": "LINALDO",
        "LOURD ES": "LOURDES",
        "LOURE NCO": "LOURENCO",
        "LUCIAN O": "LUCIANO",
        "LUCIEN E": "LUCIENE",
        "MACED O": "MACEDO",
        "MANOE L": "MANOEL",
        "MARCE LO": "MARCELO",
        "MARCI O": "MARCIO",
        "MARCIA NA": "MARCIANA",
        "MARCO LINO": "MARCOLINO",
        "MARCO S": "MARCOS",
        "MARIAN O": "MARIANO",
        "MARILE NE": "MARILENE",
        "MARILU ZE": "MARILUZE",
        "MARIN HO": "MARINHO",
        "MARQU ES": "MARQUES",
        "MARTIN S": "MARTINS",
        "MAXSU EL": "MAXSUEL",
        "MAYCO N": "MAYCON",
        "MEDEIR OS": "MEDEIROS",
        "MEIREL ES": "MEIRELES",
        "MENDE S": "MENDES",
        "MENEZ ES": "MENEZES",
        "MESSIA S": "MESSIAS",
        "MICHEL LE": "MICHELLE",
        "MONTA LVAN": "MONTALVAN",
        "MONTEI RO": "MONTEIRO",
        "MORAE S": "MORAES",
        "NASCIM ENTO": "NASCIMENTO",
        "NCELO S": "NCELOS",
        "NEYLT ON": "NEYLTON",
        "NIVAL DO": "NIVALDO",
        "NOGUE IRA": "NOGUEIRA",
        "OLIVEI RA": "OLIVEIRA",
        "PACELL I": "PACELLI",
        "PACHE CO": "PACHECO",
        "PALMEI RA": "PALMEIRA",
        "PASTO R": "PASTOR",
        "PAULIN O": "PAULINO",
        "PEREIR A": "PEREIRA",
        "PIMENT EL": "PIMENTEL",
        "PORFI RIO": "PORFIRIO",
        "PORFIR IO": "PORFIRIO",
        "QUEIR OZ": "QUEIROZ",
        "QUINTI NO": "QUINTINO",
        "QUIRIN O": "QUIRINO",
        "QUITER IA": "QUITERIA",
        "RIBEIR O": "RIBEIRO",
        "RICARD O": "RICARDO",
        "ROBER TA": "ROBERTA",
        "ROBSO N": "ROBSON",
        "RODRI GUES": "RODRIGUES",
        "ROLLE MBERG": "ROLLEMBERG",
        "ROSEA NE": "ROSEANE",
        "ROSIET H": "ROSIETH",
        "ROSINE IDE": "ROSINEIDE",
        "SANDR O": "SANDRO",
        "SANTA NA": "SANTANA",
        "SANTO S": "SANTOS",
        "SARAD A": "SARADA",
        "SEBAS TIAO": "SEBASTIAO",
        "SERGI O": "SERGIO",
        "SEVERI NA": "SEVERINA",
        "SEVERI NO": "SEVERINO",
        "SILVIN O": "SILVINO",
        "SOARE S": "SOARES",
        "SOCOR RO": "SOCORRO",
        "SOLAN GE": "SOLANGE",
        "SORIAN O": "SORIANO",
        "TENOR IO": "TENORIO",
        "TENORI O": "TENORIO",
        "TIBURC IO": "TIBURCIO",
        "TORRE S": "TORRES",
        "VALERI A": "VALERIA",
        "VALTE R": "VALTER",
        "VASCO NCELO S": "VASCONCELOS",
        "VASCO NCELOS": "VASCONCELOS",
        "VERUS CA": "VERUSCA",
        "VIEIR A": "VIEIRA",
        "VITORI NO": "VITORINO",
        "WAGNE R": "WAGNER",
        "WELLIT ON": "WELLITON",
        "ZENILD A": "ZENILDA"
    }

    # Run replacements multiple times to handle nested splits
    old_s = ""
    while old_s != s:
        old_s = s
        for key, val in replacements.items():
            s = s.replace(key, val)
            
    # Clean up double spaces
    s = " ".join(s.split())
    return s

def parse_pdf(pdf_path):
    reader = pypdf.PdfReader(pdf_path)
    records = []

    for page_idx, page in enumerate(reader.pages):
        text = page.extract_text()
        lines = [line.strip() for line in text.split('\n') if line.strip()]
        
        idx = 0
            
        record_segments = []
        current_segment = []
        
        while idx < len(lines):
            if re.match(r'^\d{7}$', lines[idx]) and idx + 1 < len(lines) and lines[idx+1] == "-":
                if current_segment:
                    record_segments.append(current_segment)
                current_segment = [lines[idx]]
            else:
                if current_segment:
                    current_segment.append(lines[idx])
            idx += 1
            
        if current_segment:
            record_segments.append(current_segment)
            
        for seg in record_segments:
            try:
                proc_parts = []
                p_idx = 0
                while p_idx < len(seg) and p_idx < 5:
                    proc_parts.append(seg[p_idx])
                    p_idx += 1
                
                processo = "".join(proc_parts).replace("..", ".")
                
                esp_idx = -1
                especialidade = ""
                specialties = ["médico", "psiquiatr", "ortopedi", "mdico", "oftalmo", "neuro", "cardiolo", "reumat", "engen", "contab", "assist", "social", "fisiot", "psicol", "fono", "odonto", "terap", "reabil"]
                for i in range(p_idx, len(seg)):
                    if any(spec in seg[i].lower() for spec in specialties):
                        esp_idx = i
                        especialidade = seg[i]
                        break
                
                if esp_idx == -1:
                    continue
                    
                perito_start_idx = esp_idx + 1
                if esp_idx + 1 < len(seg):
                    combined = (seg[esp_idx] + seg[esp_idx+1]).lower()
                    if any(spec in combined for spec in specialties):
                        perito_start_idx = esp_idx + 2

                perito_parts = []
                cpf_idx = -1
                for i in range(perito_start_idx, len(seg)):
                    perito_parts.append(seg[i])
                    if re.search(r'\d{3}-\d{2}', seg[i]):
                        cpf_idx = i
                        break
                
                perito_full = " ".join(perito_parts)
                perito_name = perito_full
                match_perito = re.match(r'^([^-]+)', perito_full)
                if match_perito:
                    perito_name = match_perito.group(1).strip()
                
                if cpf_idx == -1:
                    continue
                    
                date_idx = -1
                for i in range(cpf_idx + 1, len(seg)):
                    if re.search(r'\d{2}/\d{2}/\d{2}', seg[i]):
                        date_idx = i
                        break
                
                if date_idx == -1:
                    continue
                
                data_pericia = seg[date_idx]
                val_start_idx = date_idx + 1
                if val_start_idx < len(seg) and re.match(r'^\d{2}$', seg[val_start_idx]):
                    data_pericia += seg[val_start_idx]
                    val_start_idx += 1
                
                valor = ""
                periciado_start_idx = val_start_idx
                if val_start_idx < len(seg):
                    if seg[val_start_idx].lower() == "null":
                        valor = "null"
                        periciado_start_idx += 1
                    elif seg[val_start_idx] == "R$" and val_start_idx + 1 < len(seg):
                        valor = "R$ " + seg[val_start_idx + 1]
                        periciado_start_idx += 2
                
                periciado_parts = []
                for i in range(periciado_start_idx, len(seg)):
                    if seg[i] in ["Designa", "da", "Designada"]:
                        break
                    periciado_parts.append(seg[i])
                
                periciado = " ".join(periciado_parts)
                
                records.append({
                    "processo": processo,
                    "perito": clean_name(perito_name),
                    "periciado": clean_name(periciado)
                })
            except Exception as e:
                continue

    return records

if __name__ == "__main__":
    if len(sys.argv) < 2:
        pdf_file = r"C:\Users\aerciompr\Downloads\report.pdf"
    else:
        pdf_file = sys.argv[1]
        
    res = parse_pdf(pdf_file)
    print(json.dumps(res, indent=4, ensure_ascii=False))
