from tkinter import *
from product_management import save_product_to_db, display_products_by_platform
from log_management import log_search

def submit_data():
    # Ambil data dari entry fields
    nama_produk = entry_name.get()
    harga = entry_price.get()
    rating = entry_rating.get()
    ulasan = entry_reviews.get()
    link_gambar = entry_image.get()
    website = website_var.get()

    # Simpan data ke database
    save_product_to_db(nama_produk, harga, rating, ulasan)

def display_filtered():
    website_filter = website_var.get()
    display_products_by_platform(website_filter)

root = Tk()
root.title("Input Data Produk")

# Membuat label dan entry untuk setiap input
Label(root, text="Nama Produk:").pack()
entry_name = Entry(root)
entry_name.pack()

Label(root, text="Harga Produk:").pack()
entry_price = Entry(root)
entry_price.pack()

Label(root, text="Rating Produk:").pack()
entry_rating = Entry(root)
entry_rating.pack()

Label(root, text="Jumlah Ulasan:").pack()
entry_reviews = Entry(root)
entry_reviews.pack()

Label(root, text="Link Gambar Produk:").pack()
entry_image = Entry(root)
entry_image.pack()

website_var = StringVar(value="Tokopedia")  # Default website
Label(root, text="Pilih Website:").pack()
website_options = OptionMenu(root, website_var, "Tokopedia", "Shopee", "Blibli")
website_options.pack()

submit_button = Button(root, text="Submit", command=submit_data)
submit_button.pack()

filter_button = Button(root, text="Tampilkan Produk", command=display_filtered)
filter_button.pack()

root.mainloop()
