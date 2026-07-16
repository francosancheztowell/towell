import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { PlaneacionProvider } from '@/lib/query-provider'
import { ProgramaTejidoPage } from './programa-tejido-page'

const rootElement = document.getElementById('planeacion-programa-tejido-root')

if (rootElement) {
    const indexUrl = rootElement.dataset.indexUrl
    const legacyUrl = rootElement.dataset.legacyUrl

    if (!indexUrl || !legacyUrl) {
        throw new Error('Faltan los contratos de ruta para Programa de Tejido.')
    }

    createRoot(rootElement).render(
        <StrictMode>
            <PlaneacionProvider>
                <ProgramaTejidoPage indexUrl={indexUrl} legacyUrl={legacyUrl} />
            </PlaneacionProvider>
        </StrictMode>,
    )
}
