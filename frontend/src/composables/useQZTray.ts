import { ref } from 'vue'
// @ts-ignore
import qz from 'qz-tray'
import { api } from '@/stores/auth'

export function useQZTray() {
  const isConnected = ref(false)
  const isConnecting = ref(false)
  const error = ref<string | null>(null)

  // Force SHA512 for QZTray >= 2.1
  qz.security.setSignatureAlgorithm("SHA512")

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
      api.post('/guest/qz-sign', { request: toSign })
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

    const config = qz.configs.create(printerName, { encoding: 'ISO-8859-1' })

    // ESC/POS Commands
    const ESC = '\x1B'
    const GS = '\x1D'

    const init = ESC + '@' // Initialize
    const center = ESC + 'a' + '\x01' // Center align
    const left = ESC + 'a' + '\x00' // Left align
    const boldOn = ESC + 'E' + '\x01' // Bold on
    const boldOff = ESC + 'E' + '\x00' // Bold off
    const normalSize = GS + '!' + '\x00' // Normal text size
    const doubleSize = GS + '!' + '\x11' // Double width/height
    const hugeSize = GS + '!' + '\x22' // Triple width/height
    const cut = GS + 'V' + '\x41' + '\x10' // Full cut

    // Construct receipt
    const receiptLines = [
      init,
      center,
      normalSize, boldOn,
      appName + '\n',
      '--------------------------------\n',
      boldOff, normalSize,
      'NOMOR ANTREAN\n\n',
      hugeSize, boldOn,
      ticketData.queue_number + '\n\n',
      normalSize, boldOff,
      'Layanan: ', boldOn, ticketData.service_name + '\n',
      normalSize, boldOff,
      '--------------------------------\n',
      'Tanggal: ' + ticketData.date + '\n\n',
      'Tunggu Antrean Anda Dipanggil\n',
      cut
    ]

    const data = [
      {
        type: 'raw',
        format: 'plain',
        data: receiptLines.join('')
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
