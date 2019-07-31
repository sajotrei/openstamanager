<?php

namespace Modules\PrimaNota;

use Common\Model;
use Modules\Fatture\Fattura;
use Modules\Scadenzario\Scadenza;

class Movimento extends Model
{
    protected $table = 'co_movimenti';

    protected $appends = [
        'id_conto',
        'avere',
        'dare',
    ];

    public static function build(PrimaNota $prima_nota, $id_conto, Scadenza $scadenza = null)
    {
        $model = parent::build();

        $model->idmastrino = $prima_nota->idmastrino;
        $model->data = $prima_nota->data;
        $model->descrizione = $prima_nota->descrizione;
        $model->primanota = $prima_nota->primanota;
        $model->is_insoluto = $prima_nota->is_insoluto;

        $model->id_scadenza = $scadenza ? $scadenza->id : null;

        $documento = $scadenza ? $scadenza->documento : null;
        if (!empty($documento)) {
            $model->data_documento = $documento->data;
            $model->iddocumento = $documento->id;
            $model->idanagrafica = $documento->idanagrafica;
        }

        $model->idconto = $id_conto;

        $model->save();

        return $model;
    }

    public function setTotale($avere, $dare)
    {
        if (!empty($avere)) {
            $totale = -$avere;
        } else {
            $totale = $dare;
        }

        $this->totale = $totale;
    }

    // Attributi

    public function getIdContoAttribute()
    {
        return $this->attributes['idconto'];
    }

    public function getAvereAttribute()
    {
        return $this->totale < 0 ? abs($this->totale) : 0;
    }

    public function getDareAttribute()
    {
        return $this->totale > 0 ? abs($this->totale) : 0;
    }

    // Relazioni Eloquent

    public function scadenza()
    {
        return $this->belongsTo(Scadenza::class, 'id_scadenza');
    }

    public function documento()
    {
        return $this->belongsTo(Fattura::class, 'iddocumento');
    }
}
