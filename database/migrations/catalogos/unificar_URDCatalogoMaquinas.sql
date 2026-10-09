-- Unifica ReqTelares + InvSecuenciaTelares en URDCatalogoMaquinas: agrega Id y Secuencia.
ALTER TABLE dbo.URDCatalogoMaquinas ADD Id INT IDENTITY(1,1) NOT NULL, Secuencia INT NULL;
GO

CREATE UNIQUE INDEX UX_URDCatalogoMaquinas_Id ON dbo.URDCatalogoMaquinas (Id);

UPDATE m SET Secuencia = s.Secuencia
FROM dbo.URDCatalogoMaquinas m
JOIN dbo.InvSecuenciaTelares s ON s.NoTelar = m.MaquinaId;
