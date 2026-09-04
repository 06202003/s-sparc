from wordcloud import WordCloud, STOPWORDS
import matplotlib.pyplot as plt

# --- FILTER JAWABAN USER UNTUK WORD CLOUD ---
raw_text = """
Yang paling berdampak selama menggunakan S-SPARC adalah gamification dan dashboard
Jumlah token dan dashboardnya
Terdapat environmental impact yang menjelaskan berapa pemakaian yang telah dilakukan
Carbon footprint dan water usage
dalam memikirkan penggunaan prompting dengan keys yang telah disediakan
Dengan adanya fitur yang menampilkan dampak prompt saya pada lingkungan, saya dapat aware dengan dampak dari setiap prompt yang saya gunakan
pengetahuan akan penggunaan resource per prompt
poin gamifikasi
efisiensi prompting
Yang paling berdampak adalah melihat carbon footprint nya
dashboard
tampilan dan kecepatan ai dalam menjawab
Ga banyak ngeles
Tampilan dashboard tentang penggunaan energi
efek CO2e yang dihasilkan oleh penggunaan AI
menambah pengetahuan baru jika setiap prompt akan menghabis kan air dan menghasilkan carbon footprint
Panjngan dari promt nya
Mengurangi jumlah kata-kata dalam prompt
memastikan tiap prompt meaningfull
Pencatatan Carbon footprint
bagaimana cara mendapat perhitungannya
metrik penggunaan seharusnya dibreakdown lebih kecil karena melihat angka seperti 0,014L merasa melebih lebihkan penggunaan
fitur copy code menjadi copy prompt
terkadang masih ada error tidak menjawab pertanyaan
Server down
mungkin informasi saat menggunakan model mana yang digunakan belum terlalu jelas terlihat
fitur copy nya belum bisa dipake
pembatasan 200 kata kurang efektif dan membuat saya kesulitan untuk menanyakan logika sederhana
Informasi nya kalau bisa ada fitur plan
Fakta yang kurang mengenai sustainability, (takut terasa gimmick)
chatbot tapi fungsi chat sendiri kurang karena pembahasan dan explanasi agak kurang
Menjaga awareness di kedepannya terkait co emisi dan efisiensi propting
AI dapat menjadi masalah jika boros energi, tapi juga solusi besar untuk keberlanjutan jika dikembangkan secara bertanggung jawab
membantu dunia yang lebih eco friendly
sustainibilty sangat berpengaruh dalam skala global yang membantu membentuk konsep awal sustainibility bagi anak anak muda
Sustainability di dalam AI harus mulai diberitahu kepada orang-orang banyak agar kedepannya lingkungan kita tidak menjadi rusak hanya karena hal sepele (pengunaan AI)
sangat penting, karna dengan adanya peran tersebut kita sebagai pengguna dapat lebih peduli dalam melakukan tindakan
sustainability memberikan pengaruh yang cukup berdampak bagi dunia, mungkin sangat bagus apabila dijadikan pembelajaran dalam AI education
sangat penting karna sekarang semua sedang berusaha untuk mengurangi emisi, dan menggunakan energi terbarukan oleh karna itu penggunaan efsinsi sangat penting
untuk membatasi konsumsi sda dan meningkatkan efisiensi penggunaan ai
Bagus dan bisa menanam karakter menggunakan ai lebih efesien
Menarik sih, pelajar pasti akan dituntut untuk lebih efisien dalam penggunaan AI
Sangat keren dan membuka wawasan baru bagi saya yg belum terlalu mengenal konsep2 sustainibility
Project ini baik, hal ini bisa membuat orang-orang lebih aware dengan lingkungan sekitar nya karena mereka bisa sadar bahwa ternyata selama ini penggunaan AI dapat membuat lingkungan menjadi rusak
server semoga bisa lebih baik
bgaus untuk projectnya dan masih banyak yang bisa dikembangkan
Menarik, bisa menambah pengetahuan mengenai cara kerja AI
leaderboard bisa menjadi double edge sword yang dimana bisa saja ada yang test prompt lumayan banyak hanya untuk point
Sudah baik, namun dapat di tingkatkan lagi dari sisi riwayat chat dimana bot tidak dapat mengingat prompt pertama padahal di chat yang sama
"""


# Lowercase semua text dan filter baris kosong
lines = [l.strip().lower() for l in raw_text.split('\n') if l.strip()]
text = ' '.join(lines)

# Gabung key phrases biar nggak kepisah di wordcloud
text = text.replace("carbon footprint", "carbon_footprint")
text = text.replace("water usage", "water_usage")
text = text.replace("environmental impact", "environmental_impact")
text = text.replace("energy usage", "energy_usage")
text = text.replace("efisiensi prompting", "efisiensi_prompting")
text = text.replace("dashboard tentang penggunaan energi", "dashboard_penggunaan_energi")
text = text.replace("leaderboard", "leaderboard_feature")

stopwords = set(STOPWORDS)
stopwords.update([
    # Indo/English stopwords
    "dan", "yang", "untuk", "dengan", "ada", "sudah", "karena", "lebih", "jika", "pada", "dalam", "tidak", "sangat", "bagi", "oleh", "atau", "juga", "setiap", "prompt", "fitur",
    "baik", "cukup", "oke", "bagus", "menurut", "namun", "kurang", "semoga", "bisa", "akan", "karna", "oleh", "dapat", "masih", "jadi", "hal", "yang", "ini", "dari", "dengan", "untuk", "karena", "sudah", "ada", "pada", "jika", "lebih", "tidak", "setiap", "juga", "oleh", "atau", "bagi", "sangat", "dalam", "semua", "user", "project", "research", "feedback", "fitur", "prompt", "ai", "chat", "chatbot", "server", "double", "edge", "sword", "dimana", "test", "point", "pertama", "padahal", "chat", "yang", "sama",
    # Indo custom
    "saya", "kami", "kita", "itu", "ini", "nya", "banget", "aja", "sih", "ga", "gak", "terlalu", "cukup", "lebih", "kurang", "jadi", "karena", "seperti"
])

wordcloud = WordCloud(width=800, height=400, background_color='white', stopwords=stopwords, collocations=True).generate(text)

plt.figure(figsize=(12, 6))
plt.imshow(wordcloud, interpolation='bilinear')
plt.axis('off')
plt.show()