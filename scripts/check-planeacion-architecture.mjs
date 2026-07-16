import { readdir, readFile } from 'node:fs/promises'
import path from 'node:path'

const roots = [
    path.resolve('resources/js/react'),
    path.resolve('resources/js/planeacion'),
]

const sourceExtensions = new Set(['.ts', '.tsx'])
const violations = []

async function visit(directory) {
    const entries = await readdir(directory, { withFileTypes: true })

    for (const entry of entries) {
        const target = path.join(directory, entry.name)
        if (entry.isDirectory()) {
            await visit(target)
            continue
        }

        if (!sourceExtensions.has(path.extname(entry.name))) continue

        const source = await readFile(target, 'utf8')
        const relative = path.relative(process.cwd(), target)
        const lines = source.split(/\r?\n/).length

        if (lines > 400) violations.push(`${relative}: ${lines} lineas (maximo 400)`)
        if (/\bfetch\s*\(/.test(source)) violations.push(`${relative}: usa fetch directo`)
        if (/from\s+['"]axios['"]/.test(source) && relative !== path.normalize('resources/js/react/lib/http.ts')) {
            violations.push(`${relative}: importa axios fuera del cliente HTTP compartido`)
        }
        if (/\bSwal\b|sweetalert2/i.test(source)) violations.push(`${relative}: usa SweetAlert`)
        if (/import\s+['"][^'"]+\.css['"]/.test(source)) violations.push(`${relative}: importa CSS de modulo`)
        if (/\bstyle\s*=\s*\{\{/.test(source)) violations.push(`${relative}: usa estilos inline`)
        if (/dangerouslySetInnerHTML/.test(source)) violations.push(`${relative}: usa HTML imperativo inseguro`)
        if (/(?:bg|text|border)-\$\{/.test(source)) violations.push(`${relative}: construye clases Tailwind dinamicas`)
    }
}

for (const root of roots) await visit(root)

if (violations.length > 0) {
    console.error('Violaciones de arquitectura Planeacion:\n' + violations.map((item) => `- ${item}`).join('\n'))
    process.exit(1)
}

console.log('Arquitectura Planeacion: OK')
