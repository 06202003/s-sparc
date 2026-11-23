import os
import sys
from reportlab.lib.pagesizes import A4
from reportlab.lib import colors
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, PageBreak, KeepTogether, HRFlowable
)
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.enums import TA_CENTER, TA_LEFT, TA_RIGHT, TA_JUSTIFY
from reportlab.pdfgen import canvas

class NumberedCanvas(canvas.Canvas):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self._saved_page_states = []

    def showPage(self):
        self._saved_page_states.append(dict(self.__dict__))
        self._startPage()

    def save(self):
        num_pages = len(self._saved_page_states)
        for state in self._saved_page_states:
            self.__dict__.update(state)
            self.draw_page_decorations(num_pages)
            super().showPage()
        super().save()

    def draw_page_decorations(self, page_count):
        self.saveState()
        self.setFont("Helvetica", 8)
        self.setFillColor(colors.HexColor("#64748B"))
        
        # Header (Pages > 1)
        if self._pageNumber > 1:
            self.drawString(40, A4[1] - 30, "S-SPARC & E-STRANGE — Protokol Controlled Experiment (N=20 Staff Lab)")
            self.setStrokeColor(colors.HexColor("#CBD5E1"))
            self.setLineWidth(0.5)
            self.line(40, A4[1] - 34, A4[0] - 40, A4[1] - 34)

        # Footer
        page_str = f"Halaman {self._pageNumber} dari {page_count}"
        self.drawRightString(A4[0] - 40, 25, page_str)
        self.drawString(40, 25, "Dokumen Rancangan Eksperimen Terkontrol — AI Pop Quiz & S-SPARC UI v3")
        self.setStrokeColor(colors.HexColor("#CBD5E1"))
        self.setLineWidth(0.5)
        self.line(40, 35, A4[0] - 40, 35)
        self.restoreState()

def build_experiment_pdf(output_path="SKEMA_CONTROLLED_EXPERIMENT_SSPARC_AI_QUIZ.pdf"):
    doc = SimpleDocTemplate(
        output_path,
        pagesize=A4,
        leftMargin=40,
        rightMargin=40,
        topMargin=45,
        bottomMargin=45
    )
    
    styles = getSampleStyleSheet()
    
    # Palette
    C_PRIMARY = colors.HexColor("#0F172A")    # Slate 900
    C_TEAL = colors.HexColor("#00A0A5")       # E-STRANGE Teal
    C_TEAL_DARK = colors.HexColor("#008488")  # Darker Teal
    C_TEAL_LIGHT = colors.HexColor("#E6FFFA") # Soft Teal tint
    C_MUTED = colors.HexColor("#64748B")      # Slate 500
    C_BG_LIGHT = colors.HexColor("#F8FAFC")   # Slate 50
    C_BORDER = colors.HexColor("#E2E8F0")     # Slate 200
    C_ACCENT = colors.HexColor("#4F46E5")     # Indigo 600

    # Custom Typography Styles
    title_style = ParagraphStyle(
        'DocTitle', parent=styles['Heading1'],
        fontName='Helvetica-Bold', fontSize=18, leading=22,
        textColor=C_PRIMARY, alignment=TA_CENTER, spaceAfter=4
    )
    subtitle_style = ParagraphStyle(
        'DocSub', parent=styles['Normal'],
        fontName='Helvetica-Bold', fontSize=10, leading=14,
        textColor=C_TEAL, alignment=TA_CENTER, spaceAfter=14
    )
    meta_style = ParagraphStyle(
        'DocMeta', parent=styles['Normal'],
        fontName='Helvetica', fontSize=8.5, leading=12,
        textColor=C_MUTED, alignment=TA_CENTER, spaceAfter=16
    )
    h1_style = ParagraphStyle(
        'Heading1_Custom', parent=styles['Heading2'],
        fontName='Helvetica-Bold', fontSize=12, leading=16,
        textColor=C_PRIMARY, spaceBefore=12, spaceAfter=6
    )
    h2_style = ParagraphStyle(
        'Heading2_Custom', parent=styles['Heading3'],
        fontName='Helvetica-Bold', fontSize=10, leading=14,
        textColor=C_TEAL_DARK, spaceBefore=8, spaceAfter=4
    )
    body_style = ParagraphStyle(
        'Body_Custom', parent=styles['Normal'],
        fontName='Helvetica', fontSize=9, leading=13.5,
        textColor=colors.HexColor("#1E293B"), alignment=TA_JUSTIFY, spaceAfter=6
    )
    bullet_style = ParagraphStyle(
        'Bullet_Custom', parent=body_style,
        leftIndent=15, bulletIndent=5, spaceAfter=4
    )
    callout_style = ParagraphStyle(
        'Callout', parent=styles['Normal'],
        fontName='Helvetica-Oblique', fontSize=8.5, leading=12.5,
        textColor=colors.HexColor("#0F766E")
    )
    table_cell = ParagraphStyle(
        'TableCell', parent=styles['Normal'],
        fontName='Helvetica', fontSize=8, leading=11,
        textColor=colors.HexColor("#1E293B")
    )
    table_cell_bold = ParagraphStyle(
        'TableCellBold', parent=table_cell,
        fontName='Helvetica-Bold'
    )
    table_header = ParagraphStyle(
        'TableHeader', parent=styles['Normal'],
        fontName='Helvetica-Bold', fontSize=8.5, leading=11.5,
        textColor=colors.white, alignment=TA_CENTER
    )

    story = []

    # Title Banner
    story.append(Paragraph("SKEMA CONTROLLED EXPERIMENT", title_style))
    story.append(Paragraph("EVALUASI FITUR AI POP QUIZ DIAGNOSTIK & USER EXPERIENCE ANTARMUKA BARU S-SPARC", subtitle_style))
    story.append(Paragraph("Target Partisipan: 20 Asisten & Staff Laboratorium Komputer | Desain: 2×2 Counterbalanced Crossover A/B Testing", meta_style))
    story.append(HRFlowable(width="100%", thickness=1.5, color=C_TEAL, spaceBefore=0, spaceAfter=12))

    # 1. Ringkasan Eksekutif & Rasional Desain
    story.append(Paragraph("1. Rasional Metodologi: Mengapa Menggunakan 2×2 Crossover Design?", h1_style))
    story.append(Paragraph(
        "Dengan total subjek <b>N = 20 orang staff laboratorium</b> yang memiliki tingkat keahlian teknis (programming aptitude) di atas rata-rata mahasiswa reguler, membagi peserta secara langsung ke dalam 2 kelompok terpisah (Between-Subjects: 10 Kontrol vs 10 Eksperimen) memiliki <b>kelemahan fatal</b>: variasi kemampuan individu (individual differences) dapat menimbulkan bias statistik yang tinggi (misal: 2-3 programmer kompetitif di satu grup akan mendistorsi seluruh metrik waktu dan akurasi).",
        body_style
    ))
    story.append(Paragraph(
        "Oleh karena itu, metodologi ilmiah standar emas dalam riset Human-Computer Interaction (HCI) dan Educational Technology adalah <b>Counterbalanced Within-Subjects Crossover Design</b>. Dalam desain ini, <b>setiap peserta akan menguji KEDUA sistem</b> (Baseline vs S-SPARC Baru + AI Quiz) secara bergantian pada dua paket soal yang setara, sehingga <b>setiap peserta berfungsi sebagai kontrol bagi dirinya sendiri</b>, mengeliminasi bias kemampuan personal, dan melipatgandakan data observasi secara statistik.",
        body_style
    ))

    # Box Callout
    callout_data = [[
        Paragraph("<b>Inti Strategi Perbandingan:</b><br/>"
                  "• <b>6 Soal</b> dibagi menjadi <b>Paket Alpha (Soal 1–3)</b> dan <b>Paket Beta (Soal 4–6)</b> dengan bobot kesulitan setara.<br/>"
                  "• <b>Kelompok 1 (10 orang)</b>: Sesi 1 menggunakan Baseline (Tanpa AI Quiz) → Sesi 2 menggunakan S-SPARC Baru (+ AI Quiz).<br/>"
                  "• <b>Kelompok 2 (10 orang)</b>: Sesi 1 menggunakan S-SPARC Baru (+ AI Quiz) → Sesi 2 menggunakan Baseline (Tanpa AI Quiz).<br/>"
                  "• Urutan rotasi ini meniadakan <i>order effect</i> (efek kelelahan atau efek terbiasa).", callout_style)
    ]]
    callout_table = Table(callout_data, colWidths=[doc.width])
    callout_table.setStyle(TableStyle([
        ('BACKGROUND', (0,0), (-1,-1), C_TEAL_LIGHT),
        ('BOX', (0,0), (-1,-1), 1, C_TEAL),
        ('PADDING', (0,0), (-1,-1), 8),
        ('TOPPADDING', (0,0), (-1,-1), 6),
        ('BOTTOMPADDING', (0,0), (-1,-1), 6),
    ]))
    story.append(callout_table)
    story.append(Spacer(1, 10))

    # 2. Matriks Desain Eksperimen
    story.append(Paragraph("2. Matriks Rotasi Eksperimen (Counterbalanced Matrix)", h1_style))
    matrix_data = [
        [
            Paragraph("Kelompok", table_header),
            Paragraph("Jumlah", table_header),
            Paragraph("Tahap 1 (Sesi I - 50 Menit)", table_header),
            Paragraph("Jeda (15 Menit)", table_header),
            Paragraph("Tahap 2 (Sesi II - 50 Menit)", table_header),
            Paragraph("Evaluasi Akhir", table_header),
        ],
        [
            Paragraph("<b>Kelompok 1 (G1)</b>", table_cell_bold),
            Paragraph("10 Orang", table_cell),
            Paragraph("<b>Kondisi Kontrol (Baseline):</b><br/>• Paket Alpha (Soal 1, 2, 3)<br/>• UI Standar / Konvensional<br/>• Tanpa Fitur AI Pop Quiz", table_cell),
            Paragraph("Break 15m + SUS Sesi 1", table_cell),
            Paragraph("<b>Kondisi Eksperimen (S-SPARC):</b><br/>• Paket Beta (Soal 4, 5, 6)<br/>• UI S-SPARC Baru + C-I-O-E<br/>• <b>Fitur AI Pop Quiz 3 Soal</b>", table_cell),
            Paragraph("Post-Study Survey & Wawancara UX", table_cell)
        ],
        [
            Paragraph("<b>Kelompok 2 (G2)</b>", table_cell_bold),
            Paragraph("10 Orang", table_cell),
            Paragraph("<b>Kondisi Eksperimen (S-SPARC):</b><br/>• Paket Alpha (Soal 1, 2, 3)<br/>• UI S-SPARC Baru + C-I-O-E<br/>• <b>Fitur AI Pop Quiz 3 Soal</b>", table_cell),
            Paragraph("Break 15m + SUS Sesi 1", table_cell),
            Paragraph("<b>Kondisi Kontrol (Baseline):</b><br/>• Paket Beta (Soal 4, 5, 6)<br/>• UI Standar / Konvensional<br/>• Tanpa Fitur AI Pop Quiz", table_cell),
            Paragraph("Post-Study Survey & Wawancara UX", table_cell)
        ]
    ]
    t_matrix = Table(matrix_data, colWidths=[70, 45, 150, 65, 150, 85])
    t_matrix.setStyle(TableStyle([
        ('BACKGROUND', (0,0), (-1,0), C_PRIMARY),
        ('ALIGN', (0,0), (-1,-1), 'LEFT'),
        ('VALIGN', (0,0), (-1,-1), 'TOP'),
        ('GRID', (0,0), (-1,-1), 0.5, C_BORDER),
        ('ROWBACKGROUNDS', (0,1), (-1,-1), [colors.white, C_BG_LIGHT]),
        ('PADDING', (0,0), (-1,-1), 5),
    ]))
    story.append(t_matrix)
    story.append(Spacer(1, 10))

    # 3. Apa yang Dibandingkan (Independent vs Dependent Variables)
    story.append(Paragraph("3. Variabel & Aspek Komparasi Ilmiah", h1_style))
    story.append(Paragraph(
        "Untuk menjawab pertanyaan <i>'apa yang mau dibandingin?'</i>, penelitian ini mengukur 3 dimensi utama secara kuantitatif dan kualitatif:",
        body_style
    ))

    vars_data = [
        [
            Paragraph("Dimensi Pengujian", table_header),
            Paragraph("Apa yang Dibandingkan?", table_header),
            Paragraph("Indikator & Parameter Terukur", table_header),
            Paragraph("Metode Pengumpulan", table_header)
        ],
        [
            Paragraph("<b>A. Efektivitas AI Pop Quiz (Diagnostic & Comprehension)</b>", table_cell_bold),
            Paragraph("Apakah kuis otomatis 3 soal dari AI mampu mengukur dan membuktikan bahwa peserta benar-benar memahami kode yang mereka submit (bukan sekadar copas / blind generation)?", table_cell),
            Paragraph("• <b>Diagnostic Accuracy Rate</b>: Skor benar dari 3 soal (0-100%).<br/>• <b>Correlation</b>: Korelasi skor kuis vs kualitas kode.<br/>• <b>Time-to-Answer</b>: Durasi menjawab kuis AI.<br/>• <b>Discrimination Power</b>: Keakuratan soal dalam menguji edge-case dan kompleksitas Big-O.", table_cell),
            Paragraph("Database Log (`quiz_attempts`, `quiz_score`, `submission_time`)", table_cell)
        ],
        [
            Paragraph("<b>B. Kualitas Prompting & Literasi AI (UI S-SPARC Baru)</b>", table_cell_bold),
            Paragraph("Apakah badge C-I-O-E dan indikator eco-footprint pada tampilan baru mendorong interaksi AI yang lebih bijak, matang, dan hemat energi?", table_cell),
            Paragraph("• <b>C-I-O-E Completeness Score</b> (Context, Input, Output, Error).<br/>• <b>Shannon Entropy & Token Density</b>.<br/>• <b>Cognitive Independence Index</b>: Rasio menulis sendiri vs tergantung AI.<br/>• <b>Eco Footprint</b>: Total token, Wh listrik, dan g CO2e terpakai.", table_cell),
            Paragraph("S-SPARC Telemetry Logger & Prompt Linter API", table_cell)
        ],
        [
            Paragraph("<b>C. User Experience & Perceived Usability (UX & Kognitif)</b>", table_cell_bold),
            Paragraph("Bagaimana persepsi kemudahan, kejelasan informasi, dan beban mental peserta saat menggunakan UI baru vs UI lama?", table_cell),
            Paragraph("• <b>SUS Score (System Usability Scale)</b> (Target > 75).<br/>• <b>NASA-TLX</b>: Mental Demand, Effort, Frustration.<br/>• <b>Feature Acceptance</b>: Kepuasan terhadap badge C-I-O-E dan modal deskripsi tugas.", table_cell),
            Paragraph("Kuesioner Standar SUS & NASA-TLX Form", table_cell)
        ]
    ]
    t_vars = Table(vars_data, colWidths=[110, 155, 175, 100])
    t_vars.setStyle(TableStyle([
        ('BACKGROUND', (0,0), (-1,0), C_TEAL_DARK),
        ('ALIGN', (0,0), (-1,-1), 'LEFT'),
        ('VALIGN', (0,0), (-1,-1), 'TOP'),
        ('GRID', (0,0), (-1,-1), 0.5, C_BORDER),
        ('ROWBACKGROUNDS', (0,1), (-1,-1), [colors.white, C_BG_LIGHT]),
        ('PADDING', (0,0), (-1,-1), 5),
    ]))
    story.append(t_vars)
    story.append(Spacer(1, 10))

    # Page Break for Structure & Flow
    story.append(PageBreak())

    # 4. Distribusi 6 Soal Pemrograman
    story.append(Paragraph("4. Distribusi & Spesifikasi 6 Soal Pemrograman", h1_style))
    story.append(Paragraph(
        "Ke-6 soal yang telah disiapkan harus dipetakan ke dalam dua paket dengan tingkat kesulitan (difficulty curve) dan domain konsep yang seimbang agar tidak terjadi disparitas performa:",
        body_style
    ))

    problems_data = [
        [
            Paragraph("Paket Soal", table_header),
            Paragraph("No", table_header),
            Paragraph("Domain Topik / Algoritma", table_header),
            Paragraph("Target Tingkat Kesulitan", table_header),
            Paragraph("Fokus Pertanyaan Pop Quiz AI (3 Soal)", table_header)
        ],
        [
            Paragraph("<b>Paket ALPHA</b><br/>(Dikerjakan pada Kondisi Kontrol oleh G1 / Eksperimen oleh G2)", table_cell_bold),
            Paragraph("1<br/><br/>2<br/><br/>3", table_cell_bold),
            Paragraph("<b>Soal 1</b>: String Manipulation & Hash Table<br/><br/>"
                      "<b>Soal 2</b>: Two Pointers / Array Processing<br/><br/>"
                      "<b>Soal 3</b>: Rekursi & Divide and Conquer", table_cell),
            Paragraph("Easy-Medium<br/><br/>Medium<br/><br/>Medium-Hard", table_cell),
            Paragraph("<b>Q1</b>: Alur eksekusi baris kritis kode submit.<br/>"
                      "<b>Q2</b>: Identifikasi kompleksitas Waktu O(N) & Ruang.<br/>"
                      "<b>Q3</b>: Perilaku fungsi jika input berupa <i>edge-case</i> (array kosong/negatif).", table_cell)
        ],
        [
            Paragraph("<b>Paket BETA</b><br/>(Dikerjakan pada Kondisi Eksperimen oleh G1 / Kontrol oleh G2)", table_cell_bold),
            Paragraph("4<br/><br/>5<br/><br/>6", table_cell_bold),
            Paragraph("<b>Soal 4</b>: Sliding Window / Prefix Sum<br/><br/>"
                      "<b>Soal 5</b>: Binary Search / Sorted Operations<br/><br/>"
                      "<b>Soal 6</b>: Tree Traversal / Memoized Dynamic Prog.", table_cell),
            Paragraph("Easy-Medium<br/><br/>Medium<br/><br/>Medium-Hard", table_cell),
            Paragraph("<b>Q1</b>: Alur transisi state / kondisi terminasi loop.<br/>"
                      "<b>Q2</b>: Analisis trade-off Big-O memory vs compute.<br/>"
                      "<b>Q3</b>: Respon algoritma pada input batas maksimal (boundary condition).", table_cell)
        ]
    ]
    t_problems = Table(problems_data, colWidths=[120, 25, 140, 75, 180])
    t_problems.setStyle(TableStyle([
        ('BACKGROUND', (0,0), (-1,0), C_PRIMARY),
        ('ALIGN', (0,0), (-1,-1), 'LEFT'),
        ('VALIGN', (0,0), (-1,-1), 'TOP'),
        ('GRID', (0,0), (-1,-1), 0.5, C_BORDER),
        ('ROWBACKGROUNDS', (0,1), (-1,-1), [colors.white, C_BG_LIGHT]),
        ('PADDING', (0,0), (-1,-1), 5),
    ]))
    story.append(t_problems)
    story.append(Spacer(1, 10))

    # 5. Protokol Pelaksanaan & Rundown Sesi
    story.append(Paragraph("5. Protokol Pelaksanaan & Rundown Pengujian (150 Menit)", h1_style))
    story.append(Paragraph(
        "Eksperimen dirancang agar dapat diselesaikan dalam <b>satu sesi komprehensif berdurasi 2,5 jam</b> di laboratorium komputer dengan pengawasan fasilitator:",
        body_style
    ))

    rundown_data = [
        [
            Paragraph("Waktu (Menit)", table_header),
            Paragraph("Aktivitas Eksperimen", table_header),
            Paragraph("Deskripsi Detail & Instruksi untuk Peserta", table_header),
            Paragraph("Output / Data Terkumpul", table_header)
        ],
        [
            Paragraph("00:00 – 00:15<br/>(15 menit)", table_cell_bold),
            Paragraph("<b>Briefing & Onboarding</b>", table_cell_bold),
            Paragraph("Penjelasan maksud eksperimen, penandatanganan lembar persetujuan (informed consent), dan pembagian 20 peserta ke dalam Kelompok 1 dan Kelompok 2 secara acak (randomized).", table_cell),
            Paragraph("Form Pakta Partisipasi & Profil Singkat Peserta", table_cell)
        ],
        [
            Paragraph("00:15 – 00:20<br/>(5 menit)", table_cell_bold),
            Paragraph("<b>Warm-up Trial</b>", table_cell_bold),
            Paragraph("Peserta mencoba login ke platform E-STRANGE dan menguji 1 soal latihan sederhana agar familiar dengan editor kode.", table_cell),
            Paragraph("Verifikasi Akun & Kesiapan Environment", table_cell)
        ],
        [
            Paragraph("00:20 – 01:10<br/>(50 menit)", table_cell_bold),
            Paragraph("<b>Sesi Tahap 1 (Sesi I)</b><br/>• G1: Baseline (Alpha)<br/>• G2: S-SPARC+Quiz (Alpha)", table_cell_bold),
            Paragraph("Peserta menyelesaikan 3 soal pertama sesuai kondisi grup masing-masing. Peserta pada grup eksperimen wajib mengerjakan 3 soal kuis diagnostik AI setelah mengumpulkan kode.", table_cell),
            Paragraph("Log Submission, Skor Kuis AI, C-I-O-E Telemetry, Token Footprint", table_cell)
        ],
        [
            Paragraph("01:10 – 01:25<br/>(15 menit)", table_cell_bold),
            Paragraph("<b>Jeda & Evaluasi Sesi 1</b>", table_cell_bold),
            Paragraph("Peserta rehat sejenak dan mengisi kuesioner evaluasi tengah (SUS Form Tahap 1 dan NASA-TLX beban mental Sesi 1).", table_cell),
            Paragraph("Kuesioner SUS-1 & NASA-TLX Sesi 1", table_cell)
        ],
        [
            Paragraph("01:25 – 02:15<br/>(50 menit)", table_cell_bold),
            Paragraph("<b>Sesi Tahap 2 (Sesi II)</b><br/>• G1: S-SPARC+Quiz (Beta)<br/>• G2: Baseline (Beta)", table_cell_bold),
            Paragraph("<b>Sistem dirotasi (Crossover)</b>: Kelompok 1 kini beralih ke S-SPARC Baru (+ AI Pop Quiz), sedangkan Kelompok 2 beralih ke Baseline. Mengerjakan 3 soal Paket Beta.", table_cell),
            Paragraph("Log Submission, Skor Kuis AI, C-I-O-E Telemetry, Token Footprint", table_cell)
        ],
        [
            Paragraph("02:15 – 02:30<br/>(15 menit)", table_cell_bold),
            Paragraph("<b>Evaluasi Akhir & Exit Survey</b>", table_cell_bold),
            Paragraph("Pengisian kuesioner SUS Tahap 2, komparasi preferensi UI, persepsi terhadap relevansi soal kuis AI, serta debriefing singkat.", table_cell),
            Paragraph("Kuesioner SUS-2, Komparasi UX, dan Kuesioner Persepsi AI Quiz", table_cell)
        ]
    ]
    t_rundown = Table(rundown_data, colWidths=[70, 115, 175, 100])
    t_rundown.setStyle(TableStyle([
        ('BACKGROUND', (0,0), (-1,0), C_TEAL_DARK),
        ('ALIGN', (0,0), (-1,-1), 'LEFT'),
        ('VALIGN', (0,0), (-1,-1), 'TOP'),
        ('GRID', (0,0), (-1,-1), 0.5, C_BORDER),
        ('ROWBACKGROUNDS', (0,1), (-1,-1), [colors.white, C_BG_LIGHT]),
        ('PADDING', (0,0), (-1,-1), 5),
    ]))
    story.append(t_rundown)
    story.append(Spacer(1, 10))

    # Page Break for Questionnaire & Analysis Tools
    story.append(PageBreak())

    # 6. Instrumen Kuesioner: SUS & NASA-TLX
    story.append(Paragraph("6. Instrumen Pengukuran Kualitatif & Persepsi", h1_style))
    story.append(Paragraph(
        "Untuk memvalidasi aspek <b>Tampilan Baru S-SPARC</b> dan <b>Fitur AI Pop Quiz</b>, digunakan 3 instrumen standar yang sudah teruji validitasnya secara internasional:",
        body_style
    ))

    # SUS Table
    story.append(Paragraph("A. Kuesioner System Usability Scale (SUS) Standar (10 Pernyataan Skala 1-5)", h2_style))
    sus_items = [
        [Paragraph("No", table_header), Paragraph("Pernyataan Evaluasi Usability (System Usability Scale)", table_header), Paragraph("Skala (1: STS s/d 5: SS)", table_header)],
        [Paragraph("1", table_cell_bold), Paragraph("Saya merasa akan sering menggunakan tampilan baru S-SPARC ini dalam kegiatan praktikum.", table_cell), Paragraph("1 — 2 — 3 — 4 — 5", table_cell)],
        [Paragraph("2", table_cell_bold), Paragraph("Saya merasa tampilan antarmuka sistem ini terlalu rumit dan membingungkan.", table_cell), Paragraph("1 — 2 — 3 — 4 — 5", table_cell)],
        [Paragraph("3", table_cell_bold), Paragraph("Saya merasa tampilan antarmuka baru S-SPARC sangat mudah digunakan.", table_cell), Paragraph("1 — 2 — 3 — 4 — 5", table_cell)],
        [Paragraph("4", table_cell_bold), Paragraph("Saya membutuhkan bantuan orang teknis lain untuk dapat memahami fitur-fitur S-SPARC.", table_cell), Paragraph("1 — 2 — 3 — 4 — 5", table_cell)],
        [Paragraph("5", table_cell_bold), Paragraph("Fitur-fitur pada tampilan baru (seperti badge C-I-O-E dan indikator eco-footprint) terintegrasi dengan baik.", table_cell), Paragraph("1 — 2 — 3 — 4 — 5", table_cell)],
        [Paragraph("6", table_cell_bold), Paragraph("Saya merasa ada terlalu banyak inkonsistensi pada sistem ini.", table_cell), Paragraph("1 — 2 — 3 — 4 — 5", table_cell)],
        [Paragraph("7", table_cell_bold), Paragraph("Saya membayangkan sebagian besar asisten/mahasiswa akan cepat mempelajari sistem baru ini.", table_cell), Paragraph("1 — 2 — 3 — 4 — 5", table_cell)],
        [Paragraph("8", table_cell_bold), Paragraph("Saya merasa sistem ini sangat janggal atau membosankan saat digunakan.", table_cell), Paragraph("1 — 2 — 3 — 4 — 5", table_cell)],
        [Paragraph("9", table_cell_bold), Paragraph("Saya merasa sangat percaya diri menggunakan sistem baru S-SPARC untuk asinkron praktikum.", table_cell), Paragraph("1 — 2 — 3 — 4 — 5", table_cell)],
        [Paragraph("10", table_cell_bold), Paragraph("Saya perlu mempelajari banyak hal baru sebelum saya dapat mengoperasikan sistem ini.", table_cell), Paragraph("1 — 2 — 3 — 4 — 5", table_cell)],
    ]
    t_sus = Table(sus_items, colWidths=[25, 340, 95])
    t_sus.setStyle(TableStyle([
        ('BACKGROUND', (0,0), (-1,0), C_PRIMARY),
        ('ALIGN', (0,0), (-1,-1), 'LEFT'),
        ('VALIGN', (0,0), (-1,-1), 'MIDDLE'),
        ('GRID', (0,0), (-1,-1), 0.5, C_BORDER),
        ('ROWBACKGROUNDS', (0,1), (-1,-1), [colors.white, C_BG_LIGHT]),
        ('PADDING', (0,0), (-1,-1), 3.5),
    ]))
    story.append(t_sus)
    story.append(Spacer(1, 8))

    # Kuesioner Khusus AI Pop Quiz
    story.append(Paragraph("B. Kuesioner Khusus Persepsi Fitur AI Pop Quiz 3 Soal (Skala Likert 1-5)", h2_style))
    quiz_survey = [
        [Paragraph("No", table_header), Paragraph("Pernyataan Evaluasi Khusus Fitur AI Pop Quiz", table_header), Paragraph("Skala (1-5)", table_header)],
        [Paragraph("Q1", table_cell_bold), Paragraph("3 soal kuis yang digenerate oleh AI memiliki relevansi yang sangat tepat dengan kode yang saya submit.", table_cell), Paragraph("1 — 2 — 3 — 4 — 5", table_cell)],
        [Paragraph("Q2", table_cell_bold), Paragraph("Tingkat kesulitan soal pop quiz AI mampu menguji pemahaman logika asli saya (bukan hafalan sintaks).", table_cell), Paragraph("1 — 2 — 3 — 4 — 5", table_cell)],
        [Paragraph("Q3", table_cell_bold), Paragraph("Adanya kuis diagnostik ini efektif dalam membedakan mahasiswa yang menulis kode sendiri vs copas AI/teman.", table_cell), Paragraph("1 — 2 — 3 — 4 — 5", table_cell)],
        [Paragraph("Q4", table_cell_bold), Paragraph("Feedback dan penjelasan jawaban kuis dari AI jelas dan membantu proses pembelajaran saya.", table_cell), Paragraph("1 — 2 — 3 — 4 — 5", table_cell)],
        [Paragraph("Q5", table_cell_bold), Paragraph("Waktu respons pemuatan kuis AI terasa cepat dan tidak menghambat kenyamanan praktikum.", table_cell), Paragraph("1 — 2 — 3 — 4 — 5", table_cell)],
    ]
    t_q_survey = Table(quiz_survey, colWidths=[25, 340, 95])
    t_q_survey.setStyle(TableStyle([
        ('BACKGROUND', (0,0), (-1,0), C_TEAL_DARK),
        ('ALIGN', (0,0), (-1,-1), 'LEFT'),
        ('VALIGN', (0,0), (-1,-1), 'MIDDLE'),
        ('GRID', (0,0), (-1,-1), 0.5, C_BORDER),
        ('ROWBACKGROUNDS', (0,1), (-1,-1), [colors.white, C_BG_LIGHT]),
        ('PADDING', (0,0), (-1,-1), 3.5),
    ]))
    story.append(t_q_survey)
    story.append(Spacer(1, 10))

    # 7. Rencana Analisis Statistik
    story.append(Paragraph("7. Metode Analisis Data Statistik untuk Publikasi Ilmiah", h1_style))
    story.append(Paragraph(
        "Setelah data terkumpul dari 20 peserta, berikut adalah uji statistik yang direkomendasikan untuk membuktikan signifikansi hasil penelitian:",
        body_style
    ))
    story.append(Paragraph("• <b>Paired Sample t-Test / Wilcoxon Signed-Rank Test</b>: Digunakan untuk menguji apakah ada perbedaan signifikan antara kondisi Baseline vs S-SPARC Baru pada parameter: SUS Score, C-I-O-E Prompt Quality, dan Waktu Penyelesaian Soal.", bullet_style))
    story.append(Paragraph("• <b>Pearson / Spearman Correlation</b>: Digunakan untuk menguji korelasi antara <i>Skor Pop Quiz AI</i> dengan <i>Kualitas Kode / Indeks Plagiarisme</i> (membuktikan validitas diagnostik kuis AI).", bullet_style))
    story.append(Paragraph("• <b>Descriptive & Visual Reporting</b>: Visualisasi Radar Chart 5 Dimensi C-I-O-E, Boxplot perbandingan SUS Score, dan Diagram Alir Efisiensi Token Cloud vs Local Router.", bullet_style))

    # Build Document
    doc.build(story, canvasmaker=NumberedCanvas)
    print(f"[SUCCESS] PDF generated at: {output_path}")

if __name__ == '__main__':
    build_experiment_pdf()

