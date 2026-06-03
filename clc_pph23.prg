CLOSE TABLES all
USE jpph23_1 EXCLUSIVE
IF EMPTY(FIELD("DASAR","jpph23_1"))
	ALTER table jpph23_1 add DASAR n(11,0)
ENDIF 	 
IF EMPTY(FIELD("PPH23","jpph23_1"))
	ALTER table jpph23_1 add PPH23 n(11,0)
ENDIF 	 
IF EMPTY(date)
	=MESSAGEBOX('Invalid Date')
	CLOSE ALL
	RETURN
ENDIF 	
USE jpph23_1 EXCLUSIVE
GOTO top
refx = SPACE(LEN(reference))
recnow = 0
m.pph23 = 0
m.dasar = 0
DO WHILE !EOF()
	IF !EMPTY(reference)
		IF !EMPTY(refx) AND recnox # 0
			recnow = RECNO()
			GOTO recnox
			IF reference = refx
				lx = LEN(alltrim(reference))
				lw = LEN(ALLTRIM(payment_re))
				IF lw > 0
					m.ref = SUBSTR(reference,lw+1,lx-lw)
					replace reference WITH m.ref
				ENDIF 	 
				replace dasar WITH m.dasar,pph23 WITH m.pph23
			ENDIF 
			GOTO recnow	
		ENDIF 
		recnox = RECNO()
		refx = reference
		m.pph23 = 0
		m.dasar = 0
	ENDIF 
	IF invoice_l2="2% PPH 23"
		m.dasar = m.dasar+invoice_l3
	ENDIF 			
	IF journal_it="212003"
		m.pph23 = m.pph23 + (Journal_i2 * -1)
	ENDIF 
	SKIP
ENDDO 
IF !EMPTY(refx) AND recnox # 0
	GOTO recnox
	IF reference = refx
		replace dasar WITH m.dasar,pph23 WITH m.pph23
	ENDIF 
ENDIF 
delete all for empty(reference)
PACK
COPY TO jpph23_1 TYPE xl5
CLOSE TABLES all
		