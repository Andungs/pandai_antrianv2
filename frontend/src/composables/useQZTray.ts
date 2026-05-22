import { ref } from 'vue'
// @ts-ignore
import qz from 'qz-tray'
import { api } from '@/stores/auth'

export function useQZTray() {
  const isConnected = ref(false)
  const isConnecting = ref(false)
  const error = ref<string | null>(null)

  // Configure QZ Tray Security
  qz.security.setCertificatePromise((resolve: (value: any) => void, reject: (reason?: any) => void) => {
    // Fetch certificate from our backend
    api.get('/guest/qz-certs')
      .then(response => resolve(response.data))
      .catch(err => {
        console.error('Failed to get QZTray certificate', err)
        reject(err)
      })
  })

  qz.security.setSignaturePromise((toSign: string) => {
    return (resolve: (value: any) => void, reject: (reason?: any) => void) => {
      // Send string to backend to be signed with private key
      api.get(`/guest/qz-sign?request=${encodeURIComponent(toSign)}`)
        .then(response => resolve(response.data))
        .catch(err => {
          console.error('Failed to sign QZTray request', err)
          reject(err)
        })
    }
  })

  const connect = async () => {
    if (qz.websocket.isActive()) {
      isConnected.value = true
      return true
    }

    isConnecting.value = true
    error.value = null

    try {
      await qz.websocket.connect()
      isConnected.value = true
      return true
    } catch (e: any) {
      error.value = e.message || 'Gagal terhubung ke QZ Tray'
      isConnected.value = false
      return false
    } finally {
      isConnecting.value = false
    }
  }

  const disconnect = () => {
    if (qz.websocket.isActive()) {
      qz.websocket.disconnect()
      isConnected.value = false
    }
  }

  const printTicket = async (printerName: string, ticketData: any, appName: string = 'Pandai Antrian') => {
    if (!qz.websocket.isActive()) {
      const connected = await connect()
      if (!connected) throw new Error('QZ Tray tidak aktif')
    }

    const config = qz.configs.create(printerName, {
      size: { width: 80, height: 200 }, // 80mm thermal
      units: 'mm',
      margins: 0,
      scaleContent: false,
    })

    const htmlContent = `
      <html>
      <head>
        <style>
          body { font-family: 'Arial', sans-serif; text-align: center; margin: 0; padding: 20px; color: #000; }
          .header { font-size: 16px; font-weight: bold; margin-bottom: 5px; }
          .divider { border-top: 1px dashed #000; margin: 10px 0; }
          .title { font-size: 14px; margin-bottom: 10px; }
          .queue-number { font-size: 48px; font-weight: bold; margin: 10px 0; }
          .service-name { font-size: 18px; font-weight: bold; }
          .date { font-size: 12px; margin-top: 10px; }
          .footer { font-size: 12px; margin-top: 20px; font-weight: bold; }
        </style>
      </head>
      <body>
        <div class="header">${appName}</div>
        <div class="divider"></div>
        <div class="title">NOMOR ANTREAN</div>
        <div class="queue-number">${ticketData.queue_number}</div>
        <div class="service-name">${ticketData.service_name}</div>
        <div class="divider"></div>
        <div class="date">Tanggal: ${ticketData.date}</div>
        <div class="footer">Mohon Menunggu Antrean Anda Dipanggil</div>
      </body>
      </html>
    `

    const data = [
      {
        type: 'pixel',
        format: 'html',
        flavor: 'plain',
        data: htmlContent
      }
    ]

    try {
      await qz.print(config, data)
      return true
    } catch (e: any) {
      console.error('Print Error:', e)
      throw new Error(`Gagal mencetak: ${e.message}`)
    }
  }

  const getPrinters = async () => {
    if (!qz.websocket.isActive()) {
      await connect()
    }
    return await qz.printers.find()
  }

  return {
    isConnected,
    isConnecting,
    error,
    connect,
    disconnect,
    printTicket,
    getPrinters
  }
}
