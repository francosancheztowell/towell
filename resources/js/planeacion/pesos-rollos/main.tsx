import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { PlaneacionProvider } from '@/lib/query-provider'
import { PesosRollosPage } from './pesos-rollos-page'

const rootElement = document.getElementById('planeacion-pesos-rollos-root')

if (rootElement) {
    const indexUrl = rootElement.dataset.indexUrl
    const legacyUrl = rootElement.dataset.legacyUrl

    if (!indexUrl || !legacyUrl) {
        throw new Error('Faltan los contratos de ruta para Pesos por Rollo.')
    }

    createRoot(rootElement).render(
        <StrictMode>
            <PlaneacionProvider>
                <PesosRollosPage indexUrl={indexUrl} legacyUrl={legacyUrl} />
            </PlaneacionProvider>
        </StrictMode>,
    )
}
