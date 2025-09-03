const express = require('express');
const router = express.Router();
const db = require('../db/connection');

// Get semua produk
router.get('/', (req, res) => {
  db.query('SELECT * FROM products', (err, result) => {
    if (err) return res.status(500).json({ error: err.message });
    res.json(result);
  });
});

// Tambah produk baru
router.post('/', (req, res) => {
  const { product_name, nett_price, margin_mitra } = req.body;
  const sql = 'INSERT INTO products (product_name, nett_price, margin_mitra) VALUES (?, ?, ?)';
  db.query(sql, [product_name, nett_price, margin_mitra], (err, result) => {
    if (err) return res.status(500).json({ error: err.message });
    res.json({ message: 'Produk ditambahkan', product_id: result.insertId });
  });
});

module.exports = router;
