// objects/static/floorlamp.js — 3D модель ресурса
export function build(d = {}) {
    const g = new THREE.Group();
    const s = parseFloat(d.scale || 1.0);
    const col1 = new THREE.MeshStandardMaterial({ color: d.color || '#15803d', roughness: 0.7 });
    const col2 = new THREE.MeshStandardMaterial({ color: d.accent || '#78350f', roughness: 0.8 });
    const metalMat = new THREE.MeshStandardMaterial({ color: 0x1f2937, metalness: 0.7, roughness: 0.3 });

    const base = new THREE.Mesh(new THREE.CylinderGeometry(0.22, 0.24, 0.06, 16), metalMat);
                    base.position.y = 0.03;
                    const pole = new THREE.Mesh(new THREE.CylinderGeometry(0.025, 0.03, 1.85, 10), metalMat);
                    pole.position.y = 0.95;
                    const shade = new THREE.Mesh(new THREE.CylinderGeometry(0.22, 0.34, 0.42, 16), new THREE.MeshStandardMaterial({ color: 0xfef08a, emissive: 0xf59e0b, emissiveIntensity: 0.85 }));
                    shade.position.y = 1.9;
                    g.add(base, pole, shade);

    g.scale.set(s, s, s);
    g.traverse(m => { if (m.isMesh) { m.castShadow = true; m.receiveShadow = true; } });
    return g;
}

export default {
    id: 'floorlamp',
    category: 'static',
    staticType: 'floorlamp',
    radius: 0.45,
    build
};
