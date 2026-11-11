from __future__ import annotations

from datetime import datetime
from html import escape
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile


OUT = Path("output/BRD_SIMPEG_Refactor_Updated.docx")
OUT.parent.mkdir(parents=True, exist_ok=True)

W = "http://schemas.openxmlformats.org/wordprocessingml/2006/main"
R = "http://schemas.openxmlformats.org/officeDocument/2006/relationships"
XMLNS = f'xmlns:w="{W}" xmlns:r="{R}"'

NAVY = "17365D"
BLUE = "2F75B5"
PALE_BLUE = "EAF1F8"
PALE_GRAY = "F5F7FA"
MID_GRAY = "D9E1F2"
TEXT = "1F2937"
MUTED = "5B6573"
WHITE = "FFFFFF"
GREEN = "2E7D5B"


def esc(value: object) -> str:
    return escape(str(value), quote=False)


def rxml(text: str, *, bold: bool = False, italic: bool = False, size: int = 22,
         color: str = TEXT, font: str = "Aptos") -> str:
    props = [f'<w:rFonts w:ascii="{font}" w:hAnsi="{font}"/>',
             f'<w:sz w:val="{size}"/>', f'<w:szCs w:val="{size}"/>',
             f'<w:color w:val="{color}"/>']
    if bold:
        props.append("<w:b/>")
    if italic:
        props.append("<w:i/>")
    return f'<w:r><w:rPr>{"".join(props)}</w:rPr><w:t xml:space="preserve">{esc(text)}</w:t></w:r>'


def run(text: str, **kwargs) -> str:
    return rxml(text, **kwargs)


def p(text: str = "", *, style: str | None = None, align: str | None = None,
      size: int = 22, color: str = TEXT, bold: bool = False, italic: bool = False,
      before: int = 0, after: int = 140, line: int = 280, left: int = 0,
      first: int = 0, keep: bool = False, page_break_before: bool = False,
      runs: list[str] | None = None) -> str:
    ppr: list[str] = []
    if style:
        ppr.append(f'<w:pStyle w:val="{style}"/>')
    if align:
        ppr.append(f'<w:jc w:val="{align}"/>')
    if before or after or line:
        ppr.append(f'<w:spacing w:before="{before}" w:after="{after}" w:line="{line}" w:lineRule="auto"/>')
    if left or first:
        ppr.append(f'<w:ind w:left="{left}" w:firstLine="{first}"/>')
    if keep:
        ppr.append("<w:keepNext/>")
    if page_break_before:
        ppr.append("<w:pageBreakBefore/>")
    body = "".join(runs) if runs is not None else (rxml(text, bold=bold, italic=italic, size=size, color=color) if text else "")
    return f'<w:p><w:pPr>{"".join(ppr)}</w:pPr>{body}</w:p>'


def heading(text: str, level: int = 1, *, page_break_before: bool = False) -> str:
    if level == 1:
        return p(text, style="Heading1", size=30, color="000000", bold=True,
                 before=360, after=180, line=320, keep=True, page_break_before=page_break_before)
    return p(text, style="Heading2", size=24, color="000000", bold=True,
             before=260, after=120, line=300, keep=True)


def body(text: str, *, after: int = 160, italic: bool = False, color: str = TEXT) -> str:
    return p(text, size=21, color=color, after=after, line=310, italic=italic)


def page_break() -> str:
    return '<w:p><w:pPr><w:spacing w:after="0"/></w:pPr><w:r><w:br w:type="page"/></w:r></w:p>'


def bullet(text: str, *, level: int = 0) -> str:
    return p(runs=[run("• ", bold=True, color=BLUE, size=21), run(text, size=21, color=TEXT)],
             after=80, line=290, left=360 + level * 240, first=-180)


def cell(text: str, *, header: bool = False, shade: str | None = None,
         align: str = "left", width: int | None = None, bold: bool | None = None,
         size: int = 18) -> str:
    fill = shade or (NAVY if header else WHITE)
    text_color = WHITE if header else TEXT
    is_bold = header if bold is None else bold
    tcpr = [f'<w:shd w:fill="{fill}"/>', '<w:vAlign w:val="center"/>',
            '<w:tcMar w:top="110" w:left="120" w:bottom="110" w:right="120"/>']
    if width:
        tcpr.append(f'<w:tcW w:w="{width}" w:type="dxa"/>')
    return f'<w:tc><w:tcPr>{"".join(tcpr)}</w:tcPr>{p(text, align=align, size=size, color=text_color, bold=is_bold, after=0, line=250)}</w:tc>'


def table(rows: list[list[str]], widths: list[int], *, header: bool = True,
          font_size: int = 18, zebra: bool = True) -> str:
    borders = (
        '<w:tblBorders><w:top w:val="single" w:sz="5" w:color="D9D9D9"/>'
        '<w:left w:val="single" w:sz="5" w:color="D9D9D9"/>'
        '<w:bottom w:val="single" w:sz="5" w:color="D9D9D9"/>'
        '<w:right w:val="single" w:sz="5" w:color="D9D9D9"/>'
        '<w:insideH w:val="single" w:sz="5" w:color="D9D9D9"/>'
        '<w:insideV w:val="single" w:sz="5" w:color="D9D9D9"/></w:tblBorders>'
    )
    tblpr = f'<w:tblPr><w:tblW w:w="{sum(widths)}" w:type="dxa"/><w:tblLayout w:type="fixed"/>{borders}</w:tblPr>'
    grid = "<w:tblGrid>" + "".join(f'<w:gridCol w:w="{w}"/>' for w in widths) + "</w:tblGrid>"
    trxml = []
    for ri, row in enumerate(rows):
        is_header = header and ri == 0
        trpr = '<w:trPr><w:cantSplit/>'
        if is_header:
            trpr += '<w:tblHeader/>'
        trpr += '</w:trPr>'
        cells = []
        for ci, value in enumerate(row):
            align = "center" if ci == 0 or (is_header and ci == 0) else "left"
            if value.startswith("@@CENTER@@"):
                value = value.replace("@@CENTER@@", "", 1)
                align = "center"
            shade = None if is_header else (PALE_GRAY if zebra and ri % 2 == 0 else WHITE)
            cells.append(cell(value, header=is_header, shade=shade, align=align,
                              width=widths[ci], size=font_size))
        trxml.append(f'<w:tr>{trpr}{"".join(cells)}</w:tr>')
    return f'<w:tbl>{tblpr}{grid}{"".join(trxml)}</w:tbl>'


def metadata_table() -> str:
    rows = [
        ["Dokumen", "Business Requirements Document"],
        ["Sistem", "SIMPEG Refactor"],
        ["Basis perubahan", "PRD Simpeg.pdf dan kondisi implementasi terbaru"],
        ["Pengguna utama", "Manajemen, SDM, TI, administrator, kepala unit, dan pegawai"],
        ["Status", "Revisi BRD untuk SIMPEG Refactor"],
        ["Versi", "1.0"],
        ["Tanggal", "11 November 2026"],
    ]
    return table([["Atribut", "Keterangan"], *rows], [1900, 7400], font_size=19)


def section_title(num: str, title: str, *, page_break_before: bool = False) -> str:
    return heading(f"{num}. {title}", 1, page_break_before=page_break_before)


def page_number_field() -> str:
    return '<w:fldSimple w:instr="PAGE"><w:r><w:rPr><w:sz w:val="16"/></w:rPr><w:t>1</w:t></w:r></w:fldSimple>'


def document_xml(body_xml: str) -> str:
    sect = (
        '<w:sectPr>'
        '<w:headerReference w:type="default" r:id="rId2"/>'
        '<w:footerReference w:type="default" r:id="rId3"/>'
        '<w:pgSz w:w="12240" w:h="15840"/>'
        '<w:pgMar w:top="900" w:right="900" w:bottom="850" w:left="900" w:header="450" w:footer="450" w:gutter="0"/>'
        '<w:cols w:space="720"/>'
        '</w:sectPr>'
    )
    return f'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document {XMLNS}><w:body>{body_xml}{sect}</w:body></w:document>'


def styles_xml() -> str:
    return f'''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles {XMLNS}>
  <w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Aptos" w:hAnsi="Aptos"/><w:sz w:val="21"/><w:szCs w:val="21"/><w:color w:val="{TEXT}"/></w:rPr></w:rPrDefault><w:pPrDefault><w:pPr><w:spacing w:after="140" w:line="310" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>
  <w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/><w:rPr><w:rFonts w:ascii="Aptos" w:hAnsi="Aptos"/><w:sz w:val="21"/><w:szCs w:val="21"/><w:color w:val="{TEXT}"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:basedOn w:val="Normal"/><w:qFormat/><w:pPr><w:spacing w:after="100" w:line="320"/></w:pPr><w:rPr><w:rFonts w:ascii="Aptos Display" w:hAnsi="Aptos Display"/><w:b/><w:sz w:val="44"/><w:szCs w:val="44"/><w:color w:val="000000"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="Heading 1"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/><w:pPr><w:keepNext/><w:spacing w:before="360" w:after="180" w:line="320"/></w:pPr><w:rPr><w:rFonts w:ascii="Aptos Display" w:hAnsi="Aptos Display"/><w:b/><w:sz w:val="30"/><w:szCs w:val="30"/><w:color w:val="000000"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="Heading2"><w:name w:val="Heading 2"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/><w:pPr><w:keepNext/><w:spacing w:before="260" w:after="120" w:line="300"/></w:pPr><w:rPr><w:rFonts w:ascii="Aptos Display" w:hAnsi="Aptos Display"/><w:b/><w:sz w:val="24"/><w:szCs w:val="24"/><w:color w:val="000000"/></w:rPr></w:style>
</w:styles>'''


def header_xml() -> str:
    return f'''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:hdr {XMLNS}><w:p><w:pPr><w:jc w:val="right"/><w:spacing w:after="0"/></w:pPr>{run("SIMPEG REFACTOR  |  BRD", bold=True, size=16, color=NAVY)}</w:p></w:hdr>'''


def footer_xml() -> str:
    return f'''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:ftr {XMLNS}><w:p><w:pPr><w:jc w:val="center"/><w:spacing w:before="0" w:after="0"/></w:pPr>{run("Business Requirements Document  •  Halaman ", size=16, color=MUTED)}{page_number_field()}</w:p></w:ftr>'''


def rels_xml() -> str:
    return '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>'''


def document_rels_xml() -> str:
    return '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/>
  <Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/>
</Relationships>'''


def content_types_xml() -> str:
    return '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/>
  <Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>
  <Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>'''


def settings_xml() -> str:
    return f'''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:settings {XMLNS}><w:zoom w:percent="100"/><w:compat/><w:themeFontLang w:val="id-ID"/></w:settings>'''


def core_xml() -> str:
    return '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <dc:title>Business Requirements Document SIMPEG Refactor</dc:title>
  <dc:subject>Perubahan SIMPEG dari sistem lama ke SIMPEG Refactor</dc:subject>
  <dc:creator>SIMPEG Refactor</dc:creator>
  <cp:keywords>BRD, SIMPEG, SDM, payroll, SSO</cp:keywords>
  <dcterms:created xsi:type="dcterms:W3CDTF">2026-11-10T00:00:00Z</dcterms:created>
</cp:coreProperties>'''


def app_xml() -> str:
    return '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>SIMPEG Refactor Document Builder</Application><DocSecurity>0</DocSecurity><ScaleCrop>false</ScaleCrop><HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Heading</vt:lpstr></vt:variant><vt:variant><vt:i4>6</vt:i4></vt:variant></vt:vector></HeadingPairs></Properties>'''


def build_content() -> str:
    parts: list[str] = []
    # Cover
    parts.append(p("BUSINESS REQUIREMENTS DOCUMENT", align="center", size=18, color=BLUE, bold=True, after=180, line=300))
    parts.append(p("Perubahan SIMPEG dari Sistem Lama ke SIMPEG Refactor", style="Title", align="center", after=160, line=420, keep=True))
    parts.append(p("Acuan kebutuhan bisnis dan rencana implementasi", align="center", size=23, color=MUTED, after=440, line=330))
    parts.append(metadata_table())
    parts.append(p("", after=240))
    parts.append(p(runs=[run("Ringkasan dokumen  ", bold=True, size=21, color=NAVY), run("Dokumen ini menjelaskan alasan, sasaran, ruang lingkup, kebutuhan, dan tahapan perubahan dari SIMPEG lama ke SIMPEG Refactor. Bahasa pada bagian kebutuhan dibuat ringkas agar dapat digunakan sebagai bahan keputusan manajemen, bukan hanya sebagai spesifikasi teknis.", size=21)], after=150, line=320))
    parts.append(p(runs=[run("Prinsip utama  ", bold=True, size=21, color=GREEN), run("perubahan dilakukan bertahap dengan menjaga alur kerja, data, hak akses, dan keluaran bisnis yang sudah dipakai. Perubahan proses bisnis yang material tetap memerlukan persetujuan tersendiri.", size=21)], after=150, line=320))
    parts.append(p("", after=200))
    parts.append(p("Dokumen acuan: PRD Simpeg.pdf dan kondisi implementasi terbaru pada repository SIMPEG Refactor.", align="center", size=18, color=MUTED, italic=True, after=0, line=280))
    # Contents
    parts.append(heading("Daftar Isi", 1, page_break_before=True))
    toc = [
        ("1", "Latar Belakang"), ("2", "Tujuan Perubahan"), ("3", "Ruang Lingkup Perubahan"),
        ("4", "Fungsional Requirement"), ("5", "Non Fungsional Requirement"),
        ("6", "Tahapan Rencana Implementasi"),
        ("A", "Pemetaan perubahan PRD ke SIMPEG Refactor"),
        ("B", "Detail ERD SIMPEG Refactor"),
        ("C", "Skenario penerimaan utama"),
        ("", "Rekomendasi penyusunan dokumen"),
    ]
    for num, title in toc:
        label = f"{num}. {title}" if num else title
        parts.append(p(runs=[run(label, size=22, color=TEXT), run(" ........................................................................", size=22, color="A0A8B2")], after=90, line=290))
    parts.append(body("Catatan pembacaan: bagian 1–5 menjelaskan kebutuhan dengan istilah bisnis. Detail alur dan ERD ditempatkan pada bagian 6 agar keputusan manajemen tetap fokus pada manfaat dan kendali perubahan.", after=260, italic=True, color=MUTED))
    # 1
    parts.append(section_title("1", "Latar Belakang", page_break_before=True))
    parts.append(body("SIMPEG lama telah menjadi tempat kerja utama untuk mengelola data pegawai, riwayat kepegawaian, pengembangan pegawai, payroll, dan laporan. Sistem tersebut tetap memuat data dan aturan kerja yang penting, tetapi dibangun dengan pola lama sehingga perubahan dan perawatan menjadi semakin sulit dilakukan."))
    parts.append(body("Dalam penggunaan sehari-hari, pengguna harus berpindah di antara beberapa halaman dan pola input yang berbeda. Sebagian proses masih bergantung pada input atau import dengan validasi yang belum seragam, pencarian dan penyaringan data belum selalu mengikuti konteks kantor dan unit kerja, serta sebagian keluaran laporan perlu dibuat ulang secara manual. Di sisi lain, kebutuhan organisasi sudah berkembang: akses masuk perlu terhubung dengan SSO, pengiriman slip gaji perlu dapat dijadwalkan dan dilacak, dokumen pribadi perlu lebih aman, dan tampilan harus nyaman digunakan pada perangkat berbeda."))
    parts.append(body("Perubahan ke SIMPEG Refactor karena itu bukan dimaksudkan untuk memulai ulang proses bisnis. Arah utamanya adalah memindahkan fondasi aplikasi ke platform yang lebih mudah dirawat, kemudian memperbaiki titik-titik yang paling berpengaruh terhadap ketepatan data, keamanan, efisiensi kerja, dan pengalaman pengguna."))
    parts.append(heading("Kondisi yang ingin diperbaiki", 2))
    for item in [
        "Proses kerja dan tampilan antar-modul belum seragam sehingga pengguna membutuhkan penyesuaian berulang.",
        "Validasi pada input manual dan import belum seluruhnya konsisten dengan data referensi yang digunakan organisasi.",
        "Pengiriman slip gaji perlu membedakan tahap import, validasi tanggal gajian, publikasi, dan pengiriman agar dapat dipantau.",
        "Dokumen dan hasil laporan perlu tersedia saat dibutuhkan tanpa menyimpan salinan file hasil generate secara berlebihan.",
        "Hak akses perlu selaras dengan peran pengguna dan, untuk kebutuhan produksi, dengan akun SSO.",
    ]:
        parts.append(bullet(item))

    # 2
    parts.append(section_title("2", "Tujuan Perubahan"))
    parts.append(body("Tujuan perubahan adalah menyediakan SIMPEG yang lebih mudah digunakan, lebih aman, dan lebih siap dikembangkan, dengan tetap mempertahankan proses bisnis yang sudah dipahami oleh organisasi."))
    objectives = [
        ["T-01", "Kesinambungan kerja", "Pengguna tetap dapat menjalankan proses yang sama tanpa kehilangan data, menu penting, atau keluaran yang sudah digunakan."],
        ["T-02", "Satu sumber data", "Data pegawai, referensi organisasi, diklat, dan payroll saling terhubung sehingga mengurangi pengulangan input dan perbedaan informasi."],
        ["T-03", "Kontrol payroll", "Admin dapat memeriksa hasil import, tanggal gajian, jadwal publikasi, status pengiriman, dan penerima yang belum menerima slip."],
        ["T-04", "Keamanan dan privasi", "Akses berdasarkan peran; SSO menjadi jalur masuk produksi; dokumen pribadi dan slip hanya dapat diakses oleh pihak berwenang."],
        ["T-05", "Efisiensi biaya operasional", "PDF slip dan laporan dibuat saat diperlukan, sedangkan file upload disimpan hanya ketika memang diperlukan sebagai dokumen sumber."],
        ["T-06", "Kemudahan penggunaan", "Pencarian, filter, import, export, notifikasi, dan tampilan responsif dibuat lebih konsisten dan mudah dipahami."],
    ]
    parts.append(table([["Kode", "Sasaran", "Hasil yang diharapkan"], *objectives], [800, 1900, 6600], font_size=17))

    # 3
    parts.append(section_title("3", "Ruang Lingkup Perubahan"))
    parts.append(body("Ruang lingkup berikut menggambarkan area yang sudah menjadi bagian dari SIMPEG Refactor dan yang perlu dipastikan dalam implementasi. Perubahan pada satu area tidak boleh memutus alur di area lain."))
    scope_rows = [
        ["Termasuk", "Akses dan peran", "Login SSO yang dapat dikonfigurasi untuk lingkungan produksi, akun lokal sebagai mekanisme pengaman sesuai kebijakan, serta pembatasan menu berdasarkan peran admin, kepala, dan user."],
        ["Termasuk", "Data pegawai dan organisasi", "Daftar pegawai, kantor/area, divisi atau unit kerja, jabatan, pencarian nama atau ID pegawai, dan filter bertingkat yang mengikuti pilihan sebelumnya."],
        ["Termasuk", "Riwayat dan data pendukung", "Keluarga, pendidikan, pelatihan atau diklat, sertifikasi, pengangkatan, mutasi, hukuman, dan data referensi terkait."],
        ["Termasuk", "Payroll dan slip gaji", "Master gaji, import dan preview, validasi, perubahan dengan persetujuan, publikasi, penjadwalan, queue, notifikasi, daftar slip, serta preview atau unduh PDF."],
        ["Termasuk", "Dokumen Data Diri", "Upload PDF pribadi dengan metadata di database, penyimpanan private, preview, unduh, ganti, dan hapus dengan pengamanan akses."],
        ["Termasuk", "Import, export, dan laporan", "Template import dengan validasi, contoh pengisian dan pilihan data; export XLSX; serta laporan kepegawaian dan pengembangan dalam tampilan web, XLSX, dan PDF sesuai kebutuhan."],
        ["Termasuk", "Perbaikan UI", "Tampilan Inertia React dan Tailwind yang lebih konsisten, responsif, dan mudah digunakan tanpa mengubah nama field, parameter, dan alur utama."],
        ["Tidak termasuk", "Perubahan kebijakan bisnis", "Mengubah aturan penggajian, struktur organisasi, hak persetujuan, atau definisi data secara sepihak. Perubahan semacam ini harus disetujui sebagai kebutuhan terpisah."],
        ["Tidak termasuk", "Migrasi ulang isi data diklat lama", "Data lama tetap mengikuti sistem yang berjalan; refactor tidak melakukan pemindahan massal data diklat lama tanpa keputusan bisnis dan rencana data khusus."],
    ]
    parts.append(table([["Status", "Area", "Batasan atau cakupan"], *scope_rows], [1100, 2200, 6000], font_size=16))

    # 4
    parts.append(section_title("4", "Fungsional Requirement"))
    parts.append(body("Kebutuhan fungsional berikut ditulis sebagai kemampuan yang harus tersedia bagi pengguna. Rincian teknis seperti nama tabel, framework, dan mekanisme internal menjadi tanggung jawab tim implementasi selama hasil bisnisnya tetap terpenuhi."))
    func_rows = [
        ["FR-01", "Akses dan peran", "Pengguna dapat masuk menggunakan SSO sesuai konfigurasi produksi. Sistem mengenali peran dan hanya menampilkan tindakan yang menjadi kewenangannya."],
        ["FR-02", "Pengelolaan pegawai", "Admin dapat menambah, memperbarui, melihat, mengaktifkan atau menonaktifkan, serta mencari pegawai berdasarkan nama atau ID. Filter kantor → unit kerja → jabatan saling menyesuaikan."],
        ["FR-03", "Master dan referensi", "Data kantor, unit kerja, jabatan, golongan, eselon, dan referensi lain menjadi pilihan terarah agar input tidak bergantung pada teks bebas yang mudah berbeda penulisan."],
        ["FR-04", "Riwayat pegawai", "Data keluarga, pendidikan, pelatihan, sertifikasi, pengangkatan, mutasi, dan hukuman dapat dikelola sesuai hak akses dengan validasi yang jelas."],
        ["FR-05", "Master kegiatan diklat", "Kegiatan diklat memiliki nama, kategori, penyelenggara, tipe pelaksana, waktu, biaya, dan target peserta. Data pegawai memilih dari master sehingga penyelenggara, tahun, tanggal, dan biaya per peserta konsisten."],
        ["FR-06", "Import payroll", "Admin dapat mengunggah workbook, melihat jumlah baris dan hasil validasi, memperbaiki kesalahan sebelum disimpan, lalu menyimpan payroll tanpa mengirim slip secara diam-diam."],
        ["FR-07", "Publikasi dan pengiriman slip", "Setelah payroll valid, tanggal gajian diverifikasi terlebih dahulu. Admin dapat menjadwalkan, mengirim sekarang, membatalkan, mengubah jadwal, melihat status queue, dan mengetahui penerima yang belum memperoleh notifikasi."],
        ["FR-08", "Slip gaji", "Pegawai melihat slip miliknya melalui menu Files. Admin atau peran berwenang dapat mencari slip berdasarkan nama, ID, tahun, dan bulan. PDF dibuat saat preview atau unduh dan tidak dijadikan arsip file otomatis."],
        ["FR-09", "Dokumen Data Diri", "Pegawai mengelola dokumen miliknya; admin mengelola dokumen semua pegawai. Sistem mendukung KTP, KK, NPWP, dan Ijazah, satu file aktif per jenis, serta lihat, ganti, dan hapus."],
        ["FR-10", "Import dan export data", "Modul yang membutuhkan pemuatan data menyediakan template dengan contoh dummy dan daftar pilihan jika relevan. Export tersedia dalam format yang sesuai dan tetap mempertahankan format sumber yang dibutuhkan sistem."],
        ["FR-11", "Laporan", "Manajemen dan admin dapat membuka laporan nominatif, keadaan pegawai, formasi, pelatihan, dan daftar diklat; menerapkan filter; lalu mencetak atau mengunduh hasil tanpa menyimpan file hasil secara permanen."],
        ["FR-12", "Notifikasi dan riwayat", "Notifikasi memberi tahu slip, hasil persetujuan, dan perubahan penting. Pengguna dapat membersihkan notifikasi yang sudah dibaca; pembersihan otomatis menjaga riwayat tidak menumpuk."],
        ["FR-13", "Jejak tindakan", "Tindakan yang berisiko seperti hapus riwayat publikasi atau data penting meminta alasan dan dapat ditelusuri oleh pihak berwenang."],
    ]
    parts.append(table([["Kode", "Kemampuan", "Kebutuhan bisnis"], *func_rows], [800, 2100, 6400], font_size=15))

    # 5
    parts.append(section_title("5", "Non Fungsional Requirement"))
    parts.append(body("Kebutuhan nonfungsional menjelaskan kualitas layanan yang harus dirasakan pengguna. Ukuran angka detail dapat disepakati saat persiapan produksi, tetapi prinsip berikut wajib dijaga."))
    nonfunc_rows = [
        ["NFR-01", "Keamanan", "Data pribadi, slip gaji, dan akun hanya dapat diakses sesuai peran. Dokumen tidak boleh dibuka melalui URL publik tanpa pemeriksaan hak akses."],
        ["NFR-02", "Kerahasiaan kredensial", "Kredensial SSO dan konfigurasi koneksi tidak ditulis di source code. Jalur produksi mengikuti akun dan API SSO yang ditetapkan organisasi."],
        ["NFR-03", "Keandalan", "Kegagalan satu penerima tidak menghentikan pengiriman ke penerima lain. Sistem menyimpan status proses dan menyediakan mekanisme percobaan ulang yang terkendali."],
        ["NFR-04", "Kinerja dan skala", "Sistem tetap nyaman digunakan untuk sekitar 1.472 pegawai, proses payroll bulanan, dan ribuan riwayat slip tanpa membuat salinan PDF atau file laporan yang tidak perlu."],
        ["NFR-05", "Konsistensi data", "Validasi di sisi server menjadi sumber kebenaran. Transaksi mencegah duplikasi pegawai pada diklat, kuota atau anggaran terlampaui, dan data parsial tersimpan."],
        ["NFR-06", "Kemudahan penggunaan", "Label, bahasa, pesan kesalahan, filter, combo box, tombol, dan konfirmasi menggunakan pola yang konsisten; tampilan tetap nyaman pada desktop dan mobile."],
        ["NFR-07", "Keterpeliharaan", "Modul memiliki pola yang mudah dipahami tim pengembang: aturan akses, validasi, transaksi, layanan, dan tampilan dipisahkan secara jelas."],
        ["NFR-08", "Kompatibilitas", "Aplikasi mengikuti runtime Laravel dan PHP yang ditetapkan pada lingkungan deploy, serta diuji pada browser yang digunakan organisasi."],
        ["NFR-09", "Operasional", "Pengiriman otomatis bergantung pada scheduler dan worker queue yang aktif di server. Tersedia pencatatan kegagalan dan pemeriksaan kesehatan proses."],
        ["NFR-10", "Pemulihan", "Database dan storage memiliki jadwal backup; tersedia prosedur rollback dan pemulihan sebelum perubahan besar atau deploy produksi."],
    ]
    parts.append(table([["Kode", "Aspek", "Kebutuhan kualitas"], *nonfunc_rows], [900, 1900, 6500], font_size=16))

    # 6
    parts.append(section_title("6", "Tahapan Rencana Implementasi"))
    parts.append(body("Implementasi dilakukan bertahap. Setiap tahap memiliki hasil yang dapat diperiksa, sehingga risiko terhadap sistem yang sedang berjalan tetap rendah. Flow bisnis dan ERD ditempatkan di bagian ini sebagai panduan pelaksanaan, bukan sebagai perubahan aturan bisnis baru."))
    phase_rows = [
        ["0", "Konfirmasi baseline", "Tetapkan daftar menu, peran, laporan, format import/export, aturan payroll, dan data yang harus tetap sama. Catat perbedaan antara PRD lama dan fitur refactor terbaru."],
        ["1", "Fondasi aplikasi", "Pastikan konfigurasi lingkungan, koneksi database, penyimpanan private, session, logging, dan kompatibilitas runtime siap untuk pengembangan dan produksi."],
        ["2", "Akses dan keamanan", "Uji login SSO, pemetaan pegawai ke akun, peran, pembatasan akses, serta perilaku saat API SSO tidak tersedia. Pastikan tidak ada kredensial produksi di source code."],
        ["3", "Data pegawai dan master", "Validasi daftar pegawai, kantor, unit kerja, jabatan, dan filter bertingkat. Pastikan modul keluarga, pendidikan, pengangkatan, dan referensi tetap berjalan."],
        ["4", "Pengembangan pegawai", "Pastikan master diklat, pendaftaran peserta, kuota, anggaran, biaya per peserta, peserta, dan laporan diklat saling konsisten. Data lama tidak dipindahkan massal tanpa keputusan terpisah."],
        ["5", "Payroll, Files, dan notifikasi", "Uji alur payroll dari import hingga slip: preview → validasi → simpan → validasi tanggal gajian → publikasi/jadwal → queue → notifikasi → preview/unduh slip."],
        ["6", "Import, export, dan laporan", "Uji seluruh template yang memiliki pilihan atau tanggal, contoh pengisian, validasi baris, export XLSX, cetak atau PDF view, serta memastikan hasil generate tidak menumpuk di database atau storage."],
        ["7", "Uji regresi dan UX", "Uji tiap peran pada desktop dan mobile: pencarian, filter, form, validasi, approval, hapus dengan alasan, pagination, import, export, preview, unduh, dan 404/403."],
        ["8", "Deploy dan cutover", "Backup, jalankan migration yang disetujui, konfigurasi SSO production, aktifkan scheduler dan queue worker, smoke test, pantau log, lalu buka akses pengguna secara bertahap."],
        ["9", "Stabilisasi", "Pantau penggunaan, kegagalan queue, laporan, kapasitas database dan storage, serta kumpulkan isu pengguna untuk perbaikan terencana tanpa mengubah flow secara mendadak."],
    ]
    parts.append(table([["Tahap", "Fokus", "Hasil utama"], *phase_rows], [700, 2100, 6500], font_size=16))

    parts.append(heading("Flow bisnis utama", 2))
    flow_rows = [
        ["Akses", "Pengguna → SSO → pemetaan akun ke pegawai dan peran → dashboard sesuai kewenangan."],
        ["Data pegawai", "Master kantor → unit kerja → jabatan → data pegawai → riwayat dan laporan. Filter berikutnya hanya menampilkan pilihan yang sesuai konteks sebelumnya."],
        ["Diklat", "Master kegiatan → pilih pegawai → data penyelenggara/tanggal/biaya terisi → cek kuota dan anggaran → simpan peserta → tampilkan realisasi dan laporan."],
        ["Payroll", "Import atau master gaji → preview dan validasi → simpan payroll → validasi tanggal gajian → jadwalkan atau kirim → queue per batch → notifikasi → slip tersedia."],
        ["Data Diri", "Pilih jenis → validasi PDF dan ukuran → simpan file pada storage private + metadata → preview/unduh melalui route terlindungi → ganti atau hapus dengan konfirmasi."],
        ["Laporan", "Data sumber + filter → view laporan → cetak/PDF atau export XLSX saat diminta → hasil tidak disimpan permanen kecuali pengguna mengunduhnya."],
    ]
    parts.append(table([["Area", "Alur yang dipastikan"], *flow_rows], [1500, 7800], font_size=17))

    parts.append(heading("ERD ringkas kondisi refactor", 2))
    parts.append(body("ERD berikut adalah peta hubungan bisnis tingkat tinggi. Beberapa hubungan pada tabel legacy bersifat logis karena mengikuti struktur lama; detail foreign key dan aturan teknis mengikuti migration yang aktif pada repository."))
    erd_rows = [
        ["Pusat data", "tb_pegawai", "Menjadi pegawai utama yang terhubung dengan akun, jabatan, riwayat, keluarga, pendidikan, diklat, dan sertifikasi."],
        ["Akun", "tb_user", "Akun lokal atau shadow account SSO yang menyimpan peran dan mengarah ke pegawai; kredensial produksi mengikuti SSO."],
        ["Organisasi", "tb_kantor, tb_master_jabatan, tb_ref_jabatan, tb_masteresl, tb_mastergol", "Referensi kantor, unit kerja, jabatan, eselon, dan golongan untuk menjaga pilihan dan filter tetap akurat."],
        ["Riwayat", "tb_jabatan, tb_mutasi, tb_angkat, tb_hukuman", "Riwayat posisi dan status kepegawaian yang menjadi sumber laporan dan tampilan pegawai."],
        ["Keluarga dan pendidikan", "tb_suamiistri, tb_anak, tb_ortu, tb_pendidikan, tb_sertifikasi", "Data pendukung pegawai yang dikelola pada modul masing-masing dengan akses berbasis peran."],
        ["Diklat", "tb_biaya_pendidikan → tb_diklat", "Master kegiatan menjadi sumber penyelenggara, waktu, anggaran, target peserta, serta pendaftaran peserta dan biaya per peserta."],
        ["Payroll", "payroll_imports → payroll_entries / payroll_office_summaries; payroll_salary_masters; payroll_change_requests; payroll_publications", "Sumber data, rincian, perubahan/approval, serta publikasi dan pengiriman slip tetap dapat dilacak per periode."],
        ["Notifikasi dan queue", "tb_notifikasi, jobs, failed_jobs", "Status pemberitahuan dan pekerjaan pengiriman diproses terkontrol, dapat dipantau, dan dibersihkan sesuai kebijakan retensi."],
        ["Dokumen pribadi", "data_diri_documents → storage private", "Database menyimpan metadata; isi PDF berada di storage private dan dilayani melalui pemeriksaan akses."],
    ]
    parts.append(table([["Kelompok", "Entitas", "Hubungan dan fungsi"], *erd_rows], [1500, 3300, 4500], font_size=15))

    parts.append(heading("Indikator keberhasilan dan keputusan go live", 2))
    for item in [
        "Tidak ada kehilangan data atau perubahan aturan bisnis yang tidak disetujui.",
        "Admin, kepala, dan user hanya melihat menu serta data yang sesuai kewenangannya; login SSO production berhasil pada environment yang dituju.",
        "Import menampilkan hasil validasi yang dapat ditindaklanjuti; export dan laporan dapat dibuka atau diunduh tanpa file hasil menumpuk otomatis.",
        "Payroll dapat ditelusuri dari import sampai publikasi dan penerima; pegawai yang tidak menerima slip dapat diidentifikasi.",
        "Scheduler dan worker queue aktif, kegagalan tercatat, serta proses retry tidak mengirim notifikasi ganda.",
        "Tampilan utama dapat digunakan dengan nyaman pada desktop dan mobile, termasuk filter bertingkat dan dropdown yang tidak terpotong.",
        "Backup, rollback, dan monitoring sudah diuji sebelum akses produksi dibuka penuh.",
    ]:
        parts.append(bullet(item))

    parts.append(heading("Keputusan yang perlu dikonfirmasi manajemen", 2))
    decision_rows = [
        ["D-01", "Kebijakan retensi", "Menyetujui pembersihan notifikasi yang sudah dibaca setelah 90 hari dan mempertahankan riwayat payroll sesuai kebutuhan audit."],
        ["D-02", "Sumber login produksi", "Menetapkan SSO production sebagai sumber autentikasi utama serta prosedur saat layanan SSO sedang tidak tersedia."],
        ["D-03", "Retensi dokumen dan laporan", "Menetapkan dokumen pribadi disimpan sesuai kebutuhan administrasi; slip dan laporan dibuat saat diminta, bukan diarsipkan sebagai file otomatis."],
        ["D-04", "Operasional pengiriman", "Menetapkan penanggung jawab scheduler, queue worker, monitoring, dan penanganan penerima yang belum memiliki akun."],
    ]
    parts.append(table([["Kode", "Keputusan", "Hal yang perlu ditetapkan"], *decision_rows], [800, 2100, 6400], font_size=16))
    parts.append(body("Penutup: BRD ini menjadi acuan bersama agar migrasi ke SIMPEG Refactor menghasilkan peningkatan yang terasa bagi manajemen dan pengguna, sekaligus menjaga proses yang sudah berjalan. Perubahan di luar ruang lingkup harus dicatat sebagai kebutuhan baru agar dapat dinilai dampaknya terhadap data, keamanan, dan operasional.", after=0))

    # Lampiran A — mapping bisnis dari dokumen PRD lama ke kondisi refactor terbaru.
    parts.append(page_break())
    parts.append(heading("Lampiran A — Pemetaan perubahan PRD ke SIMPEG Refactor", 1))
    parts.append(body("Lampiran ini membantu pembaca melihat apa yang berubah dari dokumen PRD lama setelah disesuaikan dengan kondisi aplikasi terbaru. Pemetaan ini bukan daftar pekerjaan baru; ia menjadi rujukan agar fitur yang sudah berjalan tidak dianggap hilang saat proses refactor."))
    mapping_rows = [
        ["Akses", "Login lokal sebagai baseline", "SSO API menjadi jalur produksi dengan pemetaan akun ke pegawai, peran, dan penanganan gangguan layanan."],
        ["Platform", "Migrasi dari halaman PHP lama", "Laravel 13.37 + Inertia React dengan UI konsisten, responsif, mode terang/gelap, dan navbar yang dipertahankan."],
        ["Pegawai", "Daftar dan riwayat dengan filter dasar", "Filter kantor → unit kerja → jabatan saling menyesuaikan; pencarian nama/ID dan import/export mengikuti data referensi."],
        ["Diklat", "Nama diklat dan biaya dapat berupa teks bebas", "Master kegiatan terhubung ke peserta; kuota, anggaran, batas biaya per peserta, dan duplikasi divalidasi."],
        ["Payroll", "Import dan publikasi belum terpisah jelas", "Preview → validasi → simpan → tanggal gajian → jadwal/kirim → queue → tracking penerima dan kegagalan."],
        ["Files", "Slip dibaca dari payroll yang tersedia", "Slip Gaji dan Data Diri berada di Files; PDF dibuat saat diminta, dokumen pribadi memakai storage private + metadata."],
        ["Notifikasi", "Riwayat notifikasi bertumbuh tanpa kontrol retensi", "Notifikasi dapat terbaca dari pop-up, dideduplicasi, dan notifikasi yang sudah dibaca dibersihkan setelah 90 hari."],
        ["Laporan", "Cetak/export masih berbeda antar halaman", "Laporan utama memiliki filter yang konsisten, view PDF saat diminta, dan export XLSX tanpa menyimpan hasil generate otomatis."],
        ["Approval", "Pengajuan bergantung pada alur legacy", "Permintaan perubahan, status menunggu/disetujui/ditolak, alasan, notifikasi, dan hak akses dikelola secara terarah."],
    ]
    parts.append(table([["Area", "Arah pada PRD lama", "Penyesuaian pada SIMPEG Refactor"], *mapping_rows], [1300, 3100, 5100], font_size=15))

    # Lampiran B — detail ERD berdasarkan tabel yang benar-benar tersedia di refactor.
    parts.append(heading("Lampiran B — Detail ERD SIMPEG Refactor", 1))
    parts.append(body("Tabel berikut adalah detail hubungan data pada kondisi refactor saat dokumen ini disusun. Nama tabel dipertahankan karena menjadi bagian dari kompatibilitas data. Kolom yang disebutkan adalah kunci bisnis atau kolom penting, bukan seluruh daftar kolom teknis."))
    detail_erd_rows = [
        ["Induk pegawai", "tb_pegawai", "pegawai_uid, id_peg, nik, nama, tgl_lhr, status_kepeg, status_aktif, tmt_kerja, tgl_pensiun", "Pusat data pegawai; dirujuk oleh riwayat dan data pendukung."],
        ["Akun & akses", "tb_user", "id_user, nama_user, hak_akses, status_aktif, id_pegawai", "Pemetaan akun ke pegawai dan peran. SSO menjadi sumber autentikasi produksi."],
        ["Akun & akses", "rbac_permissions; rbac_access_rules", "code, label, group_name; permission_id, subject_type, subject_value, allowed", "Matriks hak akses menu dan tindakan; rules merujuk permission."],
        ["Organisasi", "tb_kantor", "kode_kantor_detail, kode_cabang, nama_kantor, level, status_kantor", "Sumber kantor/area untuk filter dan laporan."],
        ["Organisasi", "tb_master_jabatan; tb_ref_jabatan", "kode_jabatan, nama_jabatan, nama_unit_kerja, lingkup; jabatan, level, kuota, lingkup", "Sumber pilihan jabatan dan unit kerja sesuai lingkup kantor."],
        ["Organisasi", "tb_masteresl; tb_mastergol; kuota_jabatan", "id_masteresl, nama_masteresl; id_mastergol, nama_mastergol; kode_jabatan, kantor, kuota", "Referensi eselon/golongan dan kontrol kuota jabatan."],
        ["Riwayat", "tb_jabatan; tb_mutasi; tb_angkat; tb_pangkat", "id_peg, jabatan/unit_kerja, tmt, no_sk; atribut mutasi/pangkat", "Riwayat posisi, pengangkatan, mutasi, dan pangkat pegawai."],
        ["Riwayat", "tb_hukuman; tb_dp3; tb_lat_jabatan", "id_peg, hukuman, no_sk; periode/nilai; pelatih/tahun/jam", "Riwayat disiplin, penilaian, dan latihan jabatan."],
        ["Keluarga", "tb_suamiistri; tb_anak; tb_ortu", "id_peg, nama, status_hub, tgl_lhr, pekerjaan", "Data pasangan, anak, dan orang tua yang melekat pada pegawai."],
        ["Pendidikan", "tb_pendidikan; tb_bahasa", "id_peg, jenjang, nama_sekolah, jurusan, tgl_ijazah; bahasa, kemampuan", "Riwayat pendidikan dan kemampuan bahasa."],
        ["Pengembangan", "tb_master_diklat", "id_master_diklat, nama_kegiatan, kategori, penyelenggara, tipe_pelaksana, tanggal_mulai, durasi_hari, total_biaya, target_peserta", "Master kegiatan diklat; menjadi sumber pilihan, kuota, dan anggaran."],
        ["Pengembangan", "tb_biaya_pendidikan", "biaya_id, kode_pengembangan, pihak_pelaksana, tipe_pelaksana, durasi_hari, total_biaya, jumlah_sdm, tgl_pengembangan_sdm", "Referensi/biaya pengembangan yang tetap kompatibel dengan data legacy."],
        ["Pengembangan", "tb_diklat", "id_diklat, master_diklat_id, id_peg, diklat_id, biaya_per_peserta, tempat, angkatan, tahun", "Pendaftaran peserta; satu pegawai tidak boleh terdaftar dua kali pada kegiatan yang sama."],
        ["Payroll", "payroll_imports", "tahun, bulan, source_filename, source_sha256, source_row_count, is_current", "Metadata batch import dan sumber payroll; bukan penyimpanan isi XLSX."],
        ["Payroll", "payroll_entries; payroll_office_summaries", "payroll_import_id, employee_id, office_code, komponen gaji/potongan; rekap kantor", "Rincian per pegawai dan rekap per kantor, dirujuk oleh batch import."],
        ["Payroll", "payroll_salary_masters", "employee_id, office_code, tgl_gajian, komponen gaji, potongan, gaji_bersih", "Master gaji yang dapat diedit dan menjadi sumber transfer ke payroll."],
        ["Payroll", "payroll_change_requests; payroll_change_request_entries", "change_type, status, requested_by, decision_reason; employee_id, old_data, new_data", "Pengajuan perubahan payroll dan rincian perubahan per pegawai."],
        ["Publikasi", "payroll_publications", "payroll_import_id, mode, status, scheduled_at, sent_at, total_recipients, sent_count, failed_count, deletion_reason", "Tracking publikasi, jadwal, pengiriman, kegagalan, dan hapus riwayat dengan alasan."],
        ["Queue", "jobs; failed_jobs", "queue, payload, attempts, available_at; exception, failed_at", "Pekerjaan terjadwal dan kegagalan queue; perlu worker aktif di server."],
        ["Files", "data_diri_documents", "pegawai_id, jenis, nama_asli, path, ukuran, hash, diunggah_oleh", "Metadata saja di DB; isi PDF berada pada storage private, unik per pegawai dan jenis."],
        ["Notifikasi", "tb_notifikasi", "id_user, judul, pesan, link_aksi, status_baca, waktu_notif", "Notifikasi slip, approval, dan status; retensi dibatasi agar tidak menumpuk."],
        ["Audit & dukung", "tb_log_aktivitas; tb_recycle_bin; tb_config", "aksi, keterangan, waktu_log; data_json, alasan; konfigurasi aplikasi", "Jejak tindakan, pemulihan data tertentu, dan konfigurasi aplikasi."],
    ]
    parts.append(table([["Kelompok", "Tabel", "Kunci/kolom bisnis", "Hubungan dan fungsi"], *detail_erd_rows], [1250, 2200, 3350, 2700], font_size=13))
    parts.append(body("Catatan ERD: beberapa tabel legacy menggunakan relasi logis melalui id_peg, kode kantor, atau kode jabatan karena struktur sumber lama tidak selalu memakai foreign key. Hal ini harus diperhitungkan pada validasi, filter, dan pengujian regresi; tidak boleh diasumsikan semua hubungan sudah berupa constraint database.", after=140, italic=True, color=MUTED))

    # Lampiran C — data flow dan kontrol acceptance yang bisa dipakai saat UAT.
    parts.append(heading("Lampiran C — Skenario penerimaan utama", 1))
    uat_rows = [
        ["UAT-01", "Akses", "Login SSO berhasil, akun dipetakan ke pegawai yang benar, menu mengikuti peran, dan kegagalan SSO menampilkan pesan yang dapat ditindaklanjuti."],
        ["UAT-02", "Pegawai", "Pencarian nama/ID, filter kantor-unit-jabatan, tambah/edit/hapus dengan konfirmasi, pagination, import, dan export berjalan tanpa 403/404 yang tidak diharapkan."],
        ["UAT-03", "Diklat", "Pilih master → data turunan tampil → kuota/anggaran/batas biaya tervalidasi → peserta tampil di master dan laporan."],
        ["UAT-04", "Payroll", "Preview menampilkan jumlah baris sumber, validasi dapat ditindaklanjuti, transfer master tidak memerlukan import berulang, dan tanggal gajian menjadi dasar publikasi."],
        ["UAT-05", "Publikasi", "Jadwal dapat dibuat/diubah/dibatalkan; queue mengirim per batch; penerima berhasil/gagal terlihat; hapus riwayat memerlukan alasan."],
        ["UAT-06", "Files", "Slip preview/unduh dan Data Diri preview/unduh/ganti/hapus melalui route terlindungi; tidak ada file hasil generate yang menumpuk otomatis."],
        ["UAT-07", "Laporan", "Laporan nominatif, keadaan, formasi, pelatihan, dan daftar diklat dapat difilter, dibaca sebagai view PDF, dicetak, dan diekspor sesuai kebutuhan."],
        ["UAT-08", "UX", "Mode terang/gelap konsisten, sidebar collapse halus, dropdown tidak terpotong, notifikasi terbaca saat diklik, dan halaman nyaman pada mobile."],
    ]
    parts.append(table([["ID", "Area", "Skenario penerimaan"], *uat_rows], [900, 1700, 6900], font_size=15))

    parts.append(heading("Rekomendasi penyusunan dokumen", 1))
    parts.append(body("Untuk kebutuhan manajemen, bagian 1–5 sebaiknya tetap non-teknis dan berfokus pada alasan, manfaat, batasan, serta kualitas layanan. Bagian 6 boleh memakai istilah teknis secukupnya karena menjadi rencana kerja lintas SDM dan TI. Detail tabel, relasi, queue, storage, dan checklist pengujian ditempatkan di lampiran agar dokumen tetap mudah dibaca tanpa menghilangkan kedalaman yang diperlukan tim pelaksana."))
    parts.append(body("Rekomendasi ini diterapkan pada BRD ini: isi utama menjawab keputusan bisnis, sedangkan lampiran menjadi referensi tim proyek dan bahan UAT. Dengan pembagian tersebut, BRD tidak berubah menjadi dokumen teknis penuh, tetapi tetap cukup akurat untuk mengawal SIMPEG Refactor sampai produksi.", after=0))
    return "".join(parts)


def write_docx() -> None:
    body_xml = build_content()
    package = {
        "[Content_Types].xml": content_types_xml(),
        "_rels/.rels": rels_xml(),
        "word/document.xml": document_xml(body_xml),
        "word/styles.xml": styles_xml(),
        "word/header1.xml": header_xml(),
        "word/footer1.xml": footer_xml(),
        "word/settings.xml": settings_xml(),
        "word/_rels/document.xml.rels": document_rels_xml(),
        "docProps/core.xml": core_xml(),
        "docProps/app.xml": app_xml(),
    }
    with ZipFile(OUT, "w", ZIP_DEFLATED) as zf:
        for name, content in package.items():
            zf.writestr(name, content.encode("utf-8"))
    print(f"created {OUT} ({OUT.stat().st_size} bytes)")


if __name__ == "__main__":
    write_docx()
