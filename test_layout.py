import pypdf

reader = pypdf.PdfReader(r"C:\Users\aerciompr\Downloads\report.pdf")
page = reader.pages[0]
print("=== LAYOUT TEXT ===")
try:
    print(page.extract_text(extraction_mode="layout"))
except Exception as e:
    print("Layout mode not supported or failed:", e)
print("=== END LAYOUT TEXT ===")
