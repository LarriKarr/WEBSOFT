// assets/objects/animals/bird_pigeon.js
export default {
    id: 'bird_pigeon',
    name: 'Птица: Голубь',
    category: 'animals',
    radius: 0.3,
    schema: [
        { key: 'scale', label: 'Размер', type: 'range', min: 0.7, max: 1.5, step: 0.1, default: 1.0 },
        { key: 'color', label: 'Основной окрас', type: 'color', default: '#94a3b8' },
        { key: 'accent', label: 'Цвет крыльев', type: 'color', default: '#475569' }
    ],
    build(d) {
        const g = new THREE.Group();
        const s = parseFloat(d.scale || 1.0);
        const mainMat = new THREE.MeshStandardMaterial({ color: d.color || '#94a3b8', roughness: 0.8 });
        const accMat = new THREE.MeshStandardMaterial({ color: d.accent || '#475569', roughness: 0.8 });

        const body = new THREE.Mesh(new THREE.SphereGeometry(0.15, 14, 14), mainMat);
        body.scale.set(0.85, 0.9, 1.35); body.position.y = 0.24; g.add(body);

        const head = new THREE.Group(); head.name = 'head'; head.position.set(0, 0.36, 0.14);
        const hm = new THREE.Mesh(new THREE.SphereGeometry(0.09, 12, 12), mainMat);
        const beak = new THREE.Mesh(new THREE.ConeGeometry(0.035, 0.11, 8), new THREE.MeshStandardMaterial({ color: 0xf59e0b }));
        beak.rotation.x = Math.PI / 2; beak.position.set(0, -0.01, 0.12);
        head.add(hm, beak);
        
        g.add(head);

        const lw = new THREE.Group(); lw.name = 'leftArm'; lw.position.set(-0.11, 0.27, 0);
        lw.add(new THREE.Mesh(new THREE.BoxGeometry(0.24, 0.025, 0.16), accMat).translateX(-0.11));
        const rw = new THREE.Group(); rw.name = 'rightArm'; rw.position.set(0.11, 0.27, 0);
        rw.add(new THREE.Mesh(new THREE.BoxGeometry(0.24, 0.025, 0.16), accMat).translateX(0.11));
        g.add(lw, rw);

        g.scale.set(s, s, s);
        g.traverse(m => { if (m.isMesh) { m.castShadow = true; m.receiveShadow = true; } });
        return g;
    }
};
