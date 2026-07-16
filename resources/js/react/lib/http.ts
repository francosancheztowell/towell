import axios, { type AxiosRequestConfig } from 'axios'

export type ValidationErrors = Record<string, string[]>

interface ErrorPayload {
    message?: string
    errors?: ValidationErrors
}

export class HttpError extends Error {
    public constructor(
        message: string,
        public readonly status: number,
        public readonly errors: ValidationErrors = {},
    ) {
        super(message)
        this.name = 'HttpError'
    }
}

const client = axios.create({
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
})

client.interceptors.request.use((config) => {
    const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content
    if (token) config.headers.set('X-CSRF-TOKEN', token)
    return config
})

async function request<T>(config: AxiosRequestConfig): Promise<T> {
    try {
        const response = await client.request<T>(config)
        return response.data
    } catch (error) {
        if (!axios.isAxiosError<ErrorPayload>(error)) {
            throw new HttpError('Error inesperado de comunicacion.', 0)
        }

        const payload = error.response?.data
        throw new HttpError(
            payload?.message ?? error.message ?? 'No fue posible completar la solicitud.',
            error.response?.status ?? 0,
            payload?.errors ?? {},
        )
    }
}

export const apiClient = {
    get: <T>(url: string, config?: AxiosRequestConfig) => request<T>({ ...config, method: 'GET', url }),
    post: <T>(url: string, data?: unknown, config?: AxiosRequestConfig) =>
        request<T>({ ...config, method: 'POST', url, data }),
    put: <T>(url: string, data?: unknown, config?: AxiosRequestConfig) =>
        request<T>({ ...config, method: 'PUT', url, data }),
    patch: <T>(url: string, data?: unknown, config?: AxiosRequestConfig) =>
        request<T>({ ...config, method: 'PATCH', url, data }),
    delete: <T>(url: string, config?: AxiosRequestConfig) =>
        request<T>({ ...config, method: 'DELETE', url }),
}
