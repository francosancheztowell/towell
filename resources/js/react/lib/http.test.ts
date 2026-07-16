import { beforeEach, describe, expect, it, vi } from 'vitest'

const axiosMocks = vi.hoisted(() => ({
    request: vi.fn(),
    requestInterceptor: undefined as unknown,
}))

vi.mock('axios', () => ({
    default: {
        create: () => ({
            request: axiosMocks.request,
            interceptors: {
                request: {
                    use: (interceptor: unknown) => {
                        axiosMocks.requestInterceptor = interceptor
                    },
                },
            },
        }),
        isAxiosError: (error: unknown) => Boolean((error as { isAxiosError?: boolean })?.isAxiosError),
    },
}))

import { apiClient } from './http'

describe('apiClient', () => {
    beforeEach(() => {
        axiosMocks.request.mockReset()
        document.head.innerHTML = ''
    })

    it('devuelve solamente el payload data de una respuesta correcta', async () => {
        axiosMocks.request.mockResolvedValue({ data: { data: [{ id: 1 }] } })

        await expect(apiClient.get('/planeacion/api/v1/recurso')).resolves.toEqual({ data: [{ id: 1 }] })
        expect(axiosMocks.request).toHaveBeenCalledWith({ method: 'GET', url: '/planeacion/api/v1/recurso' })
    })

    it.each([403, 500])('normaliza errores HTTP %s sin exponer detalles internos', async (status) => {
        axiosMocks.request.mockRejectedValue({
            isAxiosError: true,
            message: 'Request failed',
            response: { status, data: { message: 'Solicitud rechazada.' } },
        })

        await expect(apiClient.get('/protegido')).rejects.toMatchObject({
            name: 'HttpError',
            message: 'Solicitud rechazada.',
            status,
            errors: {},
        })
    })

    it('conserva los errores por campo de Laravel 422', async () => {
        axiosMocks.request.mockRejectedValue({
            isAxiosError: true,
            response: {
                status: 422,
                data: {
                    message: 'Los datos no son validos.',
                    errors: { item_id: ['El codigo ya existe.'] },
                },
            },
        })

        await expect(apiClient.post('/recurso', {})).rejects.toMatchObject({
            status: 422,
            errors: { item_id: ['El codigo ya existe.'] },
        })
    })

    it('registra un interceptor que envia el token CSRF de Blade', () => {
        document.head.innerHTML = '<meta name="csrf-token" content="token-pruebas">'
        const interceptor = axiosMocks.requestInterceptor as
            | ((config: { headers: { set: (name: string, value: string) => void } }) => unknown)
            | undefined
        const set = vi.fn()

        expect(interceptor).toBeTypeOf('function')
        interceptor?.({ headers: { set } })
        expect(set).toHaveBeenCalledWith('X-CSRF-TOKEN', 'token-pruebas')
    })
})
