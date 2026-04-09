/**
 * Conexão MySQL para o backend Node.js
 * Usa mysql2 com pool de conexões
 */
const mysql = require('mysql2/promise')

let pool = null

function getPool () {
  if (!pool) {
    pool = mysql.createPool({
      host: process.env.DB_HOST || 'localhost',
      user: process.env.DB_USER || 'novobr',
      password: process.env.DB_PASSWORD || '',
      database: process.env.DB_NAME || 'novobr',
      waitForConnections: true,
      connectionLimit: 5,
      queueLimit: 0,
      charset: 'utf8mb4'
    })
  }
  return pool
}

async function query (sql, params = []) {
  const p = getPool()
  const [rows] = await p.execute(sql, params)
  return rows
}

async function queryOne (sql, params = []) {
  const rows = await query(sql, params)
  return rows[0] || null
}

module.exports = { getPool, query, queryOne }
